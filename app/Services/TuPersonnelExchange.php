<?php

namespace App\Services;

use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Add-only export/import kepegawaian. File hanya dibaca setelah validasi dan konfirmasi. */
final class TuPersonnelExchange
{
    private const MODULES = ['pegawai' => 'Pegawai', 'kp' => 'Kenaikan Pangkat (KP)', 'kgb' => 'Gaji Berkala (KGB)'];
    private const MAX_SEARCH = 120;

    public static function modules(string $role): array
    {
        return User::roleCanPerform($role, 'kepegawaian', 'export')
            || User::roleCanPerform($role, 'kepegawaian', 'import')
                ? self::MODULES
                : [];
    }

    public static function authorized(string $role, string $module, bool $write = false): bool
    {
        if (! array_key_exists($module, self::MODULES)) {
            return false;
        }

        return User::roleCanPerform($role, 'kepegawaian', $write ? 'import' : 'export');
    }

    private static function schema(string $module): array
    {
        return match ($module) {
            'pegawai' => [
                'columns' => ['nip','nama','email','nomor_telepon','tempat_lahir','tanggal_lahir','jenis_kelamin','alamat','status_kepegawaian','jabatan','unit_kerja','pendidikan_terakhir','tanggal_mulai_tugas','golongan_kode','tmt_pangkat_terakhir','tmt_kgb_terakhir','is_active'],
                'required' => ['nama','status_kepegawaian'],
                'dates' => ['tanggal_lahir','tanggal_mulai_tugas','tmt_pangkat_terakhir','tmt_kgb_terakhir'],
                'lengths' => ['nip'=>30,'nama'=>150,'email'=>160,'nomor_telepon'=>30,'tempat_lahir'=>100,'alamat'=>1000,'jabatan'=>120,'unit_kerja'=>120,'pendidikan_terakhir'=>100,'golongan_kode'=>20],
                'enum' => ['jenis_kelamin'=>array_keys(Pegawai::JENIS_KELAMIN), 'status_kepegawaian'=>['PNS','PPPK','Honorer'], 'is_active'=>['0','1']],
            ],
            'kp' => [
                'columns' => ['pegawai_nip','pegawai_nama','jenis','golongan_lama_kode','golongan_baru_kode','periode','tmt','nomor_sk','tanggal_sk','pejabat_penetap','status','catatan'],
                'required' => ['jenis','tmt'], 'dates' => ['tmt','tanggal_sk'],
                'lengths' => ['pegawai_nip'=>30,'pegawai_nama'=>150,'golongan_lama_kode'=>20,'golongan_baru_kode'=>20,'periode'=>30,'nomor_sk'=>100,'pejabat_penetap'=>150,'catatan'=>20000],
                'enum' => ['jenis'=>array_keys(KenaikanPangkat::JENIS_KP), 'status'=>array_keys(KenaikanPangkat::STATUSES)],
            ],
            'kgb' => [
                'columns' => ['pegawai_nip','pegawai_nama','golongan_kode','tmt','gaji_pokok_lama','gaji_pokok_baru','mk_tahun','mk_bulan','nomor_sk','tanggal_sk','pejabat_penetap','tmt_berikutnya','status','catatan'],
                'required' => ['tmt'], 'dates' => ['tmt','tanggal_sk','tmt_berikutnya'],
                'lengths' => ['pegawai_nip'=>30,'pegawai_nama'=>150,'golongan_kode'=>20,'nomor_sk'=>100,'pejabat_penetap'=>150,'catatan'=>20000],
                'enum' => ['status'=>array_keys(GajiBerkala::STATUSES)],
            ],
            default => throw new RuntimeException('Modul kepegawaian tidak tersedia.'),
        };
    }

    public static function template(string $module): array
    {
        $schema = self::schema($module);
        $examples = [
            'pegawai'=>['198001012006041001','Contoh Pegawai','pegawai@sekolah.sch.id','081234567890','Bandung','1980-01-01','L','Jl. Pendidikan 1','PNS','Staf TU','Tata Usaha','S1 Administrasi','2006-04-01','III/a','2024-04-01','2026-04-01','1'],
            'kp'=>['198001012006041001','','reguler','III/a','III/b','2026-10','2026-10-01','','','','berkas_disiapkan','Contoh data'],
            'kgb'=>['198001012006041001','','III/a','2026-10-01','3500000','3700000','8','0','','','','2028-10-01','diproses','Contoh data'],
        ];
        return [$schema['columns'], $examples[$module]];
    }

    private static function cleanDate(string $value, string $field): string
    {
        // XLSX sering mengubah tanggal menjadi serial Excel.
        if (ctype_digit($value) && (int)$value > 25000 && (int)$value < 100000) {
            $value = (new DateTimeImmutable('1899-12-30'))->modify('+'.(int)$value.' days')->format('Y-m-d');
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) throw new RuntimeException($field.' harus YYYY-MM-DD.');
        return $value;
    }

    private static function normalize(string $module, array $input): array
    {
        $cfg=self::schema($module); $out=[];
        foreach ($cfg['columns'] as $name) {
            $v=trim((string)($input[$name] ?? ''));
            if ($v==='' || $v===null) { $out[$name]=null; continue; }
            if (str_contains($v, "\0") || preg_match('/[\x01-\x08\x0B\x0C\x0E-\x1F]/', $v)) throw new RuntimeException($name.' mengandung karakter tidak sah.');
            if (isset($cfg['lengths'][$name]) && mb_strlen($v,'UTF-8') > $cfg['lengths'][$name]) throw new RuntimeException($name.' melebihi batas panjang.');
            if (in_array($name,$cfg['dates'],true)) $v=self::cleanDate($v,$name);
            if (isset($cfg['enum'][$name]) && !in_array($v,$cfg['enum'][$name],true)) throw new RuntimeException($name.' tidak sesuai pilihan.');
            $out[$name]=$v;
        }
        foreach ($cfg['required'] as $required) if ($out[$required]===null) throw new RuntimeException($required.' wajib diisi.');
        if ($module==='pegawai') {
            if ($out['nip']!==null && !preg_match('/^[A-Za-z0-9.\/-]{1,30}$/D',$out['nip'])) throw new RuntimeException('Format NIP tidak sah.');
            if ($out['email']!==null && !filter_var($out['email'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Format email tidak sah.');
            if ($out['nomor_telepon']!==null && !preg_match('/^[0-9+().\-\s]{6,30}$/D',$out['nomor_telepon'])) throw new RuntimeException('Format nomor telepon tidak sah.');
            if ($out['tanggal_lahir']!==null && $out['tanggal_lahir']>date('Y-m-d')) throw new RuntimeException('tanggal_lahir tidak boleh di masa depan.');
            if ($out['is_active']===null) $out['is_active']='1';
            if ($out['golongan_kode']) self::golonganId($out['golongan_kode']);
        } else {
            if (!$out['pegawai_nip'] && !$out['pegawai_nama']) throw new RuntimeException('Isi pegawai_nip atau pegawai_nama.');
            $pegawai=self::resolvePegawai($out);
            $out['_pegawai_id']=$pegawai->id;
            $codes=$module==='kp'?['golongan_lama_kode','golongan_baru_kode']:['golongan_kode'];
            foreach ($codes as $code) if ($out[$code]) self::golonganId($out[$code]);
            if ($module==='kp') {
                $out['status'] ??= 'berkas_disiapkan';
                if ($out['periode']!==null && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$out['periode'])) throw new RuntimeException('periode harus YYYY-MM.');
                if ($out['golongan_lama_kode'] && $out['golongan_baru_kode'] && $out['golongan_lama_kode']===$out['golongan_baru_kode']) throw new RuntimeException('Golongan baru harus berbeda dari golongan lama.');
            } else {
                $out['status'] ??= 'diproses';
                foreach (['mk_tahun'=>60,'mk_bulan'=>11] as $key=>$max) {
                    $value=$out[$key] ?? '0';
                    if (!ctype_digit((string)$value) || (int)$value>$max) throw new RuntimeException($key.' harus 0-'.$max.'.');
                    $out[$key]=(int)$value;
                }
                foreach (['gaji_pokok_lama','gaji_pokok_baru'] as $money) {
                    $value=$out[$money];
                    if ($value===null) continue;
                    if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D',$value)) throw new RuntimeException($money.' harus angka positif tanpa pemisah ribuan.');
                    $out[$money]=$value;
                }
                if ($out['gaji_pokok_lama']!==null && $out['gaji_pokok_baru']!==null && (float)$out['gaji_pokok_baru']<(float)$out['gaji_pokok_lama']) throw new RuntimeException('gaji_pokok_baru tidak boleh lebih kecil dari gaji_pokok_lama.');
                if ($out['tmt_berikutnya']!==null && $out['tmt_berikutnya']<=$out['tmt']) throw new RuntimeException('tmt_berikutnya harus setelah tmt.');
            }
        }
        return $out;
    }

    private static function golonganId(?string $code): ?int
    {
        if (!$code) return null;
        $id=RefGolongan::where('kode',$code)->value('id');
        if (!$id) throw new RuntimeException('Kode golongan tidak ditemukan: '.$code);
        return (int)$id;
    }

    private static function resolvePegawai(array $row): Pegawai
    {
        if ($row['pegawai_nip']) {
            $q=Pegawai::where('nip',$row['pegawai_nip']);
            $count=(clone $q)->count();
            if ($count !== 1) throw new RuntimeException('NIP pegawai tidak ditemukan atau tidak unik: '.$row['pegawai_nip']);
            $pegawai=$q->firstOrFail();
            if ($row['pegawai_nama'] && mb_strtolower($pegawai->nama) !== mb_strtolower($row['pegawai_nama'])) throw new RuntimeException('Nama pegawai tidak cocok dengan NIP.');
            return $pegawai;
        }
        $q=Pegawai::where('nama',$row['pegawai_nama']);
        if ((clone $q)->count()!==1) throw new RuntimeException('Nama pegawai tidak ditemukan/ambigu. Gunakan NIP.');
        return $q->firstOrFail();
    }

    private static function exists(string $module, array $row): bool
    {
        return match($module) {
            'pegawai' => ($row['nip'] ? Pegawai::withTrashed()->where('nip',$row['nip'])->exists() : Pegawai::withTrashed()->where('nama',$row['nama'])->exists()),
            'kp' => KenaikanPangkat::withTrashed()->where('pegawai_id',$row['_pegawai_id'])->where('jenis',$row['jenis'])->whereDate('tmt',$row['tmt'])->exists(),
            'kgb' => GajiBerkala::withTrashed()->where('pegawai_id',$row['_pegawai_id'])->whereDate('tmt',$row['tmt'])->exists(),
            default => throw new RuntimeException('Modul salah.'),
        };
    }

    private static function identity(string $module, array $row): string
    {
        return match($module) {
            'pegawai' => 'P|'.($row['nip'] ?: mb_strtolower($row['nama'])),
            'kp' => 'KP|'.$row['_pegawai_id'].'|'.$row['jenis'].'|'.$row['tmt'],
            'kgb' => 'KGB|'.$row['_pegawai_id'].'|'.$row['tmt'],
        };
    }

    public static function analyze(string $module, string $path): array
    {
        $cfg=self::schema($module); $file=TuDataExchange::fileRows($path);
        if (count($file)<2) throw new RuntimeException('File tidak memiliki baris data.');
        $head=array_map(static fn($v)=>strtolower(trim((string)$v)), $file[0]);
        $head[0]=preg_replace('/^\xEF\xBB\xBF/','',$head[0]);
        if (count($head)!==count(array_unique($head))) throw new RuntimeException('Nama header berulang.');
        if (count(array_diff($head,$cfg['columns']))>0) throw new RuntimeException('Kolom di luar template ditemukan.');
        foreach ($cfg['required'] as $col) if (!in_array($col,$head,true)) throw new RuntimeException('Header wajib hilang: '.$col);
        foreach ($file as $i=>$line) {
            if ($i===0) continue;
            if (count($line)>count($head) && array_filter(array_slice($line,count($head)),static fn($v)=>trim((string)$v)!=='')) throw new RuntimeException('Baris '.($i+1).': jumlah kolom berlebih.');
        }
        $valid=[]; $duplicate=[]; $errors=[]; $seen=[]; $total=0; $sample=[];
        foreach (array_slice($file,1) as $idx=>$line) {
            if (!array_filter($line,static fn($v)=>trim((string)$v)!=='')) continue;
            $total++; $number=$idx+2;
            $input=[]; foreach ($head as $i=>$name) if ($name!=='') $input[$name]=$line[$i]??'';
            try {
                $row=self::normalize($module,$input); $identity=self::identity($module,$row);
                if (isset($seen[$identity]) || self::exists($module,$row)) { $duplicate[]='Baris '.$number.': duplikat dilewati'; continue; }
                $seen[$identity]=true; $valid[]=$row;
                if (count($sample)<5) {
                    $s=[]; foreach ($cfg['columns'] as $col) $s[]=$module==='kgb' && str_starts_with($col,'gaji_pokok')?'[nominal disembunyikan]':($row[$col]??'');
                    $sample[]=$s;
                }
            } catch (RuntimeException $e) { $errors[]='Baris '.$number.': '.$e->getMessage(); }
        }
        if ($total===0) throw new RuntimeException('Tidak ada baris untuk diimpor.');
        return ['total'=>$total,'valid'=>count($valid),'duplicates'=>count($duplicate),'invalid'=>count($errors),
            'errors'=>array_slice($errors,0,30),'duplicate_messages'=>array_slice($duplicate,0,10),
            'columns'=>$cfg['columns'],'preview'=>$sample,'rows'=>$valid];
    }

    public static function import(string $module, string $path, int $userId): array
    {
        return DB::transaction(function () use ($module,$path,$userId) {
            $check=self::analyze($module,$path);
            if ($check['invalid']>0) throw new RuntimeException('Ada baris invalid; tidak ada perubahan disimpan.');
            $count=0;
            foreach ($check['rows'] as $row) {
                if ($module!=='pegawai') Pegawai::whereKey($row['_pegawai_id'])->lockForUpdate()->firstOrFail();
                if (self::exists($module,$row)) continue;
                if ($module==='pegawai') {
                    Pegawai::create([
                        'nip'=>$row['nip'], 'nama'=>$row['nama'], 'status_kepegawaian'=>$row['status_kepegawaian'],
                        'email'=>$row['email'],'nomor_telepon'=>$row['nomor_telepon'],
                        'tempat_lahir'=>$row['tempat_lahir'],'tanggal_lahir'=>$row['tanggal_lahir'],
                        'jenis_kelamin'=>$row['jenis_kelamin'],'alamat'=>$row['alamat'],
                        'jabatan'=>$row['jabatan'],'unit_kerja'=>$row['unit_kerja'],
                        'pendidikan_terakhir'=>$row['pendidikan_terakhir'],'tanggal_mulai_tugas'=>$row['tanggal_mulai_tugas'],
                        'golongan_id'=>self::golonganId($row['golongan_kode']),
                        'tmt_pangkat_terakhir'=>$row['tmt_pangkat_terakhir'],'tmt_kgb_terakhir'=>$row['tmt_kgb_terakhir'],
                        'is_active'=>(bool)$row['is_active'],
                    ]);
                } elseif ($module==='kp') {
                    KenaikanPangkat::create([
                        'pegawai_id'=>$row['_pegawai_id'],'jenis'=>$row['jenis'],
                        'golongan_lama_id'=>self::golonganId($row['golongan_lama_kode']),
                        'golongan_baru_id'=>self::golonganId($row['golongan_baru_kode']),
                        'periode'=>$row['periode'],'tmt'=>$row['tmt'], 'nomor_sk'=>$row['nomor_sk'],
                        'tanggal_sk'=>$row['tanggal_sk'],'pejabat_penetap'=>$row['pejabat_penetap'],
                        'status'=>$row['status'],'catatan'=>$row['catatan'],'created_by'=>$userId,
                    ]);
                } else {
                    GajiBerkala::create([
                        'pegawai_id'=>$row['_pegawai_id'],'golongan_id'=>self::golonganId($row['golongan_kode']),
                        'tmt'=>$row['tmt'],'tmt_berikutnya'=>$row['tmt_berikutnya'],
                        'gaji_pokok_lama'=>$row['gaji_pokok_lama'],'gaji_pokok_baru'=>$row['gaji_pokok_baru'],
                        'mk_tahun'=>$row['mk_tahun'],'mk_bulan'=>$row['mk_bulan'],
                        'nomor_sk'=>$row['nomor_sk'],'tanggal_sk'=>$row['tanggal_sk'],
                        'pejabat_penetap'=>$row['pejabat_penetap'],'status'=>$row['status'],
                        'catatan'=>$row['catatan'],'created_by'=>$userId,
                    ]);
                }
                $count++;
            }
            return ['success'=>$count,'skipped'=>$check['total']-$count,'total'=>$check['total']];
        },3);
    }

    public static function export(string $module, ?string $needle, ?string $start, ?string $end): array
    {
        $cfg=self::schema($module);
        $needle=trim((string)$needle);
        if (mb_strlen($needle,'UTF-8')>self::MAX_SEARCH) throw new RuntimeException('Pencarian maksimal 120 karakter.');
        foreach ([$start,$end] as $date) if ($date) self::cleanDate($date,'tanggal filter');
        if ($start && $end && $start>$end) throw new RuntimeException('Tanggal awal harus sebelum akhir.');
        $query=match($module) {
            'pegawai'=>Pegawai::query()->with('golongan'),
            'kp'=>KenaikanPangkat::query()->with(['pegawai','golonganLama','golonganBaru']),
            'kgb'=>GajiBerkala::query()->with(['pegawai','golongan']),
        };
        if ($needle!=='') {
            $like='%'.str_replace(['%','_'],['\\%','\\_'],$needle).'%';
            if ($module==='pegawai') $query->where(fn($q)=>$q->where('nama','like',$like)->orWhere('nip','like',$like)->orWhere('email','like',$like)->orWhere('nomor_telepon','like',$like)->orWhere('unit_kerja','like',$like));
            else $query->where(fn($q)=>$q->where('nomor_sk','like',$like)->orWhereHas('pegawai',fn($p)=>$p->where('nama','like',$like)->orWhere('nip','like',$like)));
        }
        $dateColumn=$module==='pegawai'?'created_at':'tmt';
        if ($start) $query->whereDate($dateColumn,'>=',$start);
        if ($end) $query->whereDate($dateColumn,'<=',$end);
        if ((clone $query)->count()>TuDataExchange::MAX_EXPORT) throw new RuntimeException('Ekspor lebih dari 10.000 baris, gunakan filter.');
        $rows=[$cfg['columns']];
        $query->chunkById(500, function ($items) use (&$rows,$module,$cfg) {
          foreach ($items as $item) {
            if ($module==='pegawai') $data=[
                'nip'=>$item->nip,'nama'=>$item->nama,'status_kepegawaian'=>$item->status_kepegawaian,
                'email'=>$item->email,'nomor_telepon'=>$item->nomor_telepon,
                'tempat_lahir'=>$item->tempat_lahir,'tanggal_lahir'=>$item->tanggal_lahir,
                'jenis_kelamin'=>$item->jenis_kelamin,'alamat'=>$item->alamat,
                'jabatan'=>$item->jabatan,'unit_kerja'=>$item->unit_kerja,
                'pendidikan_terakhir'=>$item->pendidikan_terakhir,'tanggal_mulai_tugas'=>$item->tanggal_mulai_tugas,
                'golongan_kode'=>$item->golongan?->kode,
                'tmt_pangkat_terakhir'=>$item->tmt_pangkat_terakhir,'tmt_kgb_terakhir'=>$item->tmt_kgb_terakhir,
                'is_active'=>$item->is_active ? 1 : 0,
            ];
            elseif ($module==='kp') $data=[
                'pegawai_nip'=>$item->pegawai?->nip,'pegawai_nama'=>$item->pegawai?->nama,'jenis'=>$item->jenis,
                'golongan_lama_kode'=>$item->golonganLama?->kode,'golongan_baru_kode'=>$item->golonganBaru?->kode,
                'periode'=>$item->periode,'tmt'=>$item->tmt,'nomor_sk'=>$item->nomor_sk,'tanggal_sk'=>$item->tanggal_sk,
                'pejabat_penetap'=>$item->pejabat_penetap,'status'=>$item->status,'catatan'=>$item->catatan,
            ];
            else $data=[
                'pegawai_nip'=>$item->pegawai?->nip,'pegawai_nama'=>$item->pegawai?->nama,
                'golongan_kode'=>$item->golongan?->kode,'tmt'=>$item->tmt,'gaji_pokok_lama'=>$item->gaji_pokok_lama,
                'gaji_pokok_baru'=>$item->gaji_pokok_baru,'mk_tahun'=>$item->mk_tahun,'mk_bulan'=>$item->mk_bulan,
                'nomor_sk'=>$item->nomor_sk,'tanggal_sk'=>$item->tanggal_sk,
                'pejabat_penetap'=>$item->pejabat_penetap,'tmt_berikutnya'=>$item->tmt_berikutnya,
                'status'=>$item->status,'catatan'=>$item->catatan,
            ];
            $rows[]=array_map(static function($key) use ($data) {
                $v=$data[$key]??null;
                return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string)($v??'');
            },$cfg['columns']);
          }
        });
        return $rows;
    }
}
