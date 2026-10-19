<?php

namespace App\Services;

use App\Models\Legalisir;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Pertukaran data administrasi TU. Tidak mengubah catatan yang ada; impor hanya menambah.
 * CSV memakai UTF-8; XLSX memakai ZIP/XML bawaan PHP (jika ekstensi tersedia).
 */
final class TuDataExchange
{
    public const MAX_ROWS = 1000;
    public const MAX_EXPORT = 10000;
    public const MAX_BYTES = 5242880;

    public static function modules(): array
    {
        return ['surat_masuk' => 'Surat Masuk', 'surat_keluar' => 'Surat Keluar', 'legalisir' => 'Legalisir'];
    }

    public static function config(string $module): array
    {
        $map = [
            'surat_masuk' => [
                'model' => SuratMasuk::class, 'date' => 'tanggal_diterima', 'search' => ['nomor_agenda', 'nomor_surat', 'pengirim', 'perihal'],
                'columns' => ['nomor_agenda','nomor_surat','tanggal_surat','tanggal_diterima','pengirim','perihal','sifat','status','lokasi_arsip_fisik'],
                'required' => ['tanggal_diterima','pengirim','perihal'],
                'dates' => ['tanggal_surat','tanggal_diterima'],
                'enums' => ['sifat' => array_keys(SuratMasuk::SIFAT), 'status' => array_keys(SuratMasuk::STATUSES)],
                'lengths' => ['nomor_agenda'=>50,'nomor_surat'=>100,'pengirim'=>200,'perihal'=>300,'lokasi_arsip_fisik'=>150],
            ],
            'surat_keluar' => [
                'model' => SuratKeluar::class, 'date' => 'tanggal_surat', 'search' => ['nomor_surat', 'tujuan', 'perihal'],
                'columns' => ['nomor_surat','tanggal_surat','tujuan','perihal','sifat','status','pembuat_konsep','lokasi_arsip_fisik'],
                'required' => ['nomor_surat','tanggal_surat','tujuan','perihal'],
                'dates' => ['tanggal_surat'],
                'enums' => ['sifat' => array_keys(SuratKeluar::SIFAT), 'status' => array_keys(SuratKeluar::STATUSES)],
                'lengths' => ['nomor_surat'=>100,'tujuan'=>200,'perihal'=>300,'pembuat_konsep'=>150,'lokasi_arsip_fisik'=>150],
            ],
            'legalisir' => [
                'model' => Legalisir::class, 'date' => 'tanggal_permohonan', 'search' => ['nomor_legalisir', 'nama_pemohon', 'kontak', 'jenis_dokumen'],
                'columns' => ['nomor_legalisir','tanggal_permohonan','nama_pemohon','kontak','status_pemohon','jenis_dokumen','jumlah_lembar','status','catatan'],
                'required' => ['tanggal_permohonan','nama_pemohon','jenis_dokumen'],
                'dates' => ['tanggal_permohonan'],
                'enums' => ['status_pemohon' => array_keys(Legalisir::STATUS_PEMOHON), 'status' => array_keys(Legalisir::STATUSES)],
                'lengths' => ['nomor_legalisir'=>50,'nama_pemohon'=>150,'kontak'=>60,'jenis_dokumen'=>100,'catatan'=>20000],
            ],
        ];
        if (!isset($map[$module])) {
            throw new RuntimeException('Modul tidak dikenal.');
        }
        return $map[$module];
    }

    public static function template(string $module): array
    {
        $config = self::config($module);
        $samples = [
            'surat_masuk' => ['','012/INST/2026','2026-10-18','2026-10-19','Dinas Pendidikan','Undangan rapat','biasa','baru','Lemari A / Rak 1'],
            'surat_keluar' => ['001/TU/2026','2026-10-19','Dinas Pendidikan','Permohonan data','biasa','draf','Staf TU','Lemari B'],
            'legalisir' => ['','2026-10-19','Nama Pemohon','081234567890','alumni','Ijazah','2','diterima','Bawa ijazah asli'],
        ];
        return [$config['columns'], $samples[$module]];
    }

    public static function fileRows(string $path): array
    {
        if (!is_file($path) || !is_readable($path) || filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('Berkas tidak ada, tidak terbaca, atau melebihi 5 MB.');
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            return self::csvRows($path);
        }
        if ($extension === 'xlsx') {
            return self::xlsxRows($path);
        }
        throw new RuntimeException('Hanya berkas .csv dan .xlsx yang diterima.');
    }

    private static function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) throw new RuntimeException('CSV tidak bisa dibuka.');
        try {
            $first = fgets($handle);
            if ($first === false) throw new RuntimeException('CSV kosong.');
            $separator = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            rewind($handle);
            $rows = [];
            while (($row = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
                if (count($rows) > self::MAX_ROWS) throw new RuntimeException('Maksimal 1.000 baris data per impor.');
                foreach ($row as $cell) {
                    if ($cell !== null && preg_match('//u', $cell) !== 1) throw new RuntimeException('CSV harus disimpan dengan encoding UTF-8.');
                    if (is_string($cell) && strlen($cell) > 25000) throw new RuntimeException('Salah satu sel terlalu panjang.');
                }
                $rows[] = $row;
            }
            return $rows;
        } finally { fclose($handle); }
    }

    private static function xlsxRows(string $path): array
    {
        if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
            throw new RuntimeException('XLSX memerlukan ekstensi PHP zip dan dom. Gunakan CSV jika belum tersedia.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('File XLSX tidak valid.');
        try {
            $uncompressed = 0;
            for ($i=0; $i<$zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $uncompressed += $stat['size'];
                if ($uncompressed > 16000000) throw new RuntimeException('Isi XLSX terlalu besar untuk diimpor.');
            }
            $shared = [];
            if (($stringsXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $doc = self::xml($stringsXml);
                $xp = new DOMXPath($doc);
                $xp->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($xp->query('//s:si') as $node) $shared[] = $node->textContent;
            }
            $sheetPath = 'xl/worksheets/sheet1.xml';
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($workbookXml !== false && $relsXml !== false) {
                $workbook = self::xml($workbookXml);
                $wx = new DOMXPath($workbook);
                $wx->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $firstSheet = $wx->query('//s:sheets/s:sheet')->item(0);
                $rid = $firstSheet?->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                $relDoc = self::xml($relsXml);
                $rx = new DOMXPath($relDoc);
                $rx->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
                foreach ($rx->query('//r:Relationship') as $relationship) {
                    if ($relationship->getAttribute('Id') === $rid) {
                        $target = $relationship->getAttribute('Target');
                        if (str_contains($target, '..') || str_contains($target, '\\') || str_starts_with($target, 'http')) {
                            throw new RuntimeException('Path worksheet Excel tidak aman.');
                        }
                        $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                        break;
                    }
                }
            }
            $sheet = $zip->getFromName($sheetPath);
            if ($sheet === false) throw new RuntimeException('Worksheet pertama tidak ditemukan dalam XLSX.');
            $doc = self::xml($sheet);
            $xp = new DOMXPath($doc);
            $xp->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];
            foreach ($xp->query('//s:sheetData/s:row') as $rowNode) {
                if (count($rows) > self::MAX_ROWS) throw new RuntimeException('Maksimal 1.000 baris data per impor.');
                $line = [];
                foreach ($xp->query('./s:c', $rowNode) as $cell) {
                    if ($xp->query('./s:f', $cell)->length) throw new RuntimeException('Rumus Excel tidak didukung saat impor. Ubah ke nilai biasa dahulu.');
                    if (!preg_match('/^([A-Z]+)\d+$/i', $cell->getAttribute('r'), $m)) continue;
                    $col = 0;
                    foreach (str_split(strtoupper($m[1])) as $char) $col = $col * 26 + ord($char) - 64;
                    if ($col < 1 || $col > 40) throw new RuntimeException('Maksimal 40 kolom XLSX.');
                    $v = $xp->query('./s:v', $cell)->item(0)?->textContent ?? '';
                    $type = $cell->getAttribute('t');
                    if ($type === 's') $v = $shared[(int)$v] ?? '';
                    elseif ($type === 'inlineStr') $v = $xp->query('./s:is', $cell)->item(0)?->textContent ?? '';
                    $line[$col - 1] = $v;
                }
                if (!$line) continue;
                $size = max(array_keys($line)) + 1;
                $rows[] = array_replace(array_fill(0, $size, ''), $line);
            }
            return $rows;
        } finally { $zip->close(); }
    }

    private static function xml(string $xml): DOMDocument
    {
        $doc = new DOMDocument();
        $old = libxml_use_internal_errors(true);
        try {
            if (!$doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) throw new RuntimeException('XML dalam XLSX rusak.');
        } finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
        return $doc;
    }

    public static function analyze(string $module, string $path): array
    {
        $cfg = self::config($module);
        $rows = self::fileRows($path);
        if (count($rows) < 2) throw new RuntimeException('File hanya berisi header atau kosong.');
        $head = array_map(fn ($v) => strtolower(trim((string)$v)), $rows[0]);
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
        if (count(array_unique($head)) !== count($head)) throw new RuntimeException('Header CSV/Excel berulang.');
        foreach ($cfg['required'] as $column) {
            if (!in_array($column, $head, true)) throw new RuntimeException('Kolom wajib hilang: '.$column);
        }
        $unknown = array_diff(array_filter($head), $cfg['columns']);
        if ($unknown) throw new RuntimeException('Kolom tidak dikenal: '.implode(', ', $unknown).'. Unduh template dari aplikasi.');
        $valid = []; $errors = []; $dupes = []; $seen = []; $total = 0; $preview = [];
        foreach (array_slice($rows, 1) as $index => $row) {
            if (!array_filter($row, fn ($v) => trim((string)$v) !== '')) continue;
            $total++; $number = $index + 2;
            $input = [];
            foreach ($head as $idx => $column) {
                if ($column !== '') $input[$column] = trim((string)($row[$idx] ?? ''));
            }
            try {
                $record = self::normalize($cfg, $input);
                $identity = self::identity($module, $record);
                if (isset($seen[$identity]) || self::exists($module, $record)) {
                    $dupes[] = 'Baris '.$number.': kemungkinan duplikat, dilewati.';
                    continue;
                }
                $seen[$identity] = true;
                $valid[] = $record;
                if (count($preview) < 5) $preview[] = array_map(fn ($field) => $record[$field] ?? '', $cfg['columns']);
            } catch (RuntimeException $e) {
                $errors[] = 'Baris '.$number.': '.$e->getMessage();
            }
        }
        if ($total === 0) throw new RuntimeException('Tidak ada data untuk diimpor.');
        return ['total'=>$total, 'valid'=>count($valid), 'duplicates'=>count($dupes), 'invalid'=>count($errors),
            'errors'=>array_slice($errors,0,30), 'duplicate_messages'=>array_slice($dupes,0,10),
            'preview'=>$preview, 'columns'=>$cfg['columns'], 'rows'=>$valid];
    }

    private static function normalize(array $cfg, array $input): array
    {
        $out = [];
        foreach ($cfg['columns'] as $field) {
            $v = $input[$field] ?? '';
            if ($v === '') { $out[$field] = null; continue; }
            if (str_contains($v, "\0")) throw new RuntimeException($field.' memuat karakter tidak sah.');
            if (isset($cfg['lengths'][$field]) && mb_strlen($v, 'UTF-8') > $cfg['lengths'][$field]) {
                throw new RuntimeException($field.' terlalu panjang.');
            }
            if (in_array($field, $cfg['dates'], true)) {
                if (is_numeric($v) && (float)$v > 25000 && (float)$v < 100000) {
                    $v = (new DateTimeImmutable('1899-12-30'))->modify('+'.(int)$v.' days')->format('Y-m-d');
                }
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) throw new RuntimeException($field.' harus YYYY-MM-DD.');
            }
            if (isset($cfg['enums'][$field])) {
                $v = strtolower($v);
                if (!in_array($v, $cfg['enums'][$field], true)) throw new RuntimeException($field.' tidak sesuai pilihan yang tersedia.');
            }
            if ($field === 'jumlah_lembar' && (!ctype_digit($v) || (int)$v < 1 || (int)$v > 10000)) {
                throw new RuntimeException('jumlah_lembar harus angka 1–10000.');
            }
            $out[$field] = $v;
        }
        foreach ($cfg['required'] as $field) {
            if ($out[$field] === null) throw new RuntimeException($field.' wajib diisi.');
        }
        return $out;
    }

    private static function identity(string $module, array $row): string
    {
        $key = match ($module) {
            'surat_masuk' => $row['nomor_agenda'] ?: implode('|', [$row['nomor_surat'], $row['pengirim'], $row['tanggal_diterima'], $row['perihal']]),
            'surat_keluar' => $row['nomor_surat'],
            'legalisir' => $row['nomor_legalisir'] ?: implode('|', [$row['nama_pemohon'],$row['tanggal_permohonan'],$row['jenis_dokumen'],$row['kontak']]),
        };
        return hash('sha256', strtolower((string)$key));
    }

    private static function exists(string $module, array $row): bool
    {
        return match ($module) {
            'surat_masuk' => $row['nomor_agenda']
                ? SuratMasuk::withTrashed()->where('nomor_agenda', $row['nomor_agenda'])->exists()
                : SuratMasuk::where('pengirim',$row['pengirim'])->where('perihal',$row['perihal'])
                    ->whereDate('tanggal_diterima',$row['tanggal_diterima'])->where('nomor_surat',$row['nomor_surat'])->exists(),
            'surat_keluar' => SuratKeluar::withTrashed()->where('nomor_surat', $row['nomor_surat'])->exists(),
            'legalisir' => $row['nomor_legalisir']
                ? Legalisir::withTrashed()->where('nomor_legalisir', $row['nomor_legalisir'])->exists()
                : Legalisir::where('nama_pemohon',$row['nama_pemohon'])->whereDate('tanggal_permohonan',$row['tanggal_permohonan'])
                    ->where('jenis_dokumen',$row['jenis_dokumen'])->where('kontak',$row['kontak'])->exists(),
        };
    }

    public static function import(string $module, string $path, int $userId): array
    {
        return DB::transaction(function () use ($module,$path,$userId) {
            $check = self::analyze($module,$path);
            if ($check['invalid'] > 0) throw new RuntimeException('Ada data tidak valid. Perbaiki file dan pratinjau ulang. Tidak ada baris disimpan.');
            $count = 0;
            foreach ($check['rows'] as $row) {
                // Race-safety: periksa kembali catatan yang sudah disimpan di transaksi ini.
                if (self::exists($module,$row)) continue;
                if ($module === 'surat_masuk') {
                    $row['nomor_agenda'] = $row['nomor_agenda'] ?: SuratMasuk::generateNomorAgenda((int)substr($row['tanggal_diterima'],0,4));
                    $row['sifat'] ??= 'biasa'; $row['status'] ??= 'baru';
                    $row['created_by'] = $userId; $row['sumber'] = 'manual';
                    SuratMasuk::create($row);
                } elseif ($module === 'surat_keluar') {
                    $row['sifat'] ??= 'biasa'; $row['status'] ??= 'draf';
                    $row['created_by'] = $userId; $row['sumber'] = 'manual';
                    SuratKeluar::create($row);
                } else {
                    $row['nomor_legalisir'] = $row['nomor_legalisir'] ?: Legalisir::generateNomorLegalisir((int)substr($row['tanggal_permohonan'],0,4));
                    $row['jumlah_lembar'] ??= 1; $row['status'] ??= 'diterima'; $row['status_pemohon'] ??= 'alumni';
                    $row['petugas_id'] = $userId;
                    Legalisir::create($row);
                }
                $count++;
            }
            return ['success'=>$count,'skipped'=>$check['total']-$count,'total'=>$check['total']];
        },3);
    }

    public static function query(string $module, ?string $search, ?string $start, ?string $end): Builder
    {
        $cfg = self::config($module);
        $query = $cfg['model']::query();
        if ($search !== null && trim($search) !== '') {
            $needle = trim($search);
            if (mb_strlen($needle,'UTF-8') > 120) throw new RuntimeException('Pencarian maksimal 120 karakter.');
            $query->where(function (Builder $q) use ($cfg,$needle) {
                foreach ($cfg['search'] as $col) $q->orWhere($col,'like','%'.str_replace(['%','_'],['\\%','\\_'],$needle).'%');
            });
        }
        foreach (['dari tanggal'=>$start,'sampai tanggal'=>$end] as $label=>$value) {
            if (!$value) continue;
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d',$value);
            if (!$dt || $dt->format('Y-m-d') !== $value) throw new RuntimeException('Format '.$label.' tidak valid.');
        }
        if ($start && $end && $start > $end) throw new RuntimeException('Tanggal mulai harus sebelum tanggal akhir.');
        return $query->when($start,fn ($q)=>$q->whereDate($cfg['date'],'>=',$start))
            ->when($end,fn ($q)=>$q->whereDate($cfg['date'],'<=',$end));
    }

    public static function exportRows(string $module, ?string $search, ?string $start, ?string $end): array
    {
        $cfg = self::config($module);
        $query = self::query($module,$search,$start,$end);
        if ((clone $query)->count() > self::MAX_EXPORT) {
            throw new RuntimeException('Ekspor maksimal 10.000 baris. Persempit filter tanggal atau pencarian.');
        }
        $rows = [$cfg['columns']];
        foreach ($query->orderBy('id')->cursor() as $record) {
            $row = [];
            foreach ($cfg['columns'] as $column) {
                $value = $record->{$column};
                $row[] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string)($value ?? '');
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public static function csvDownload(array $rows, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output','wb');
            fwrite($stream,"\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($stream,array_map(static function ($v) {
                    $v = (string)$v;
                    return preg_match('/^[\s\x00-\x1F]*[=+@\-]/u', $v) ? "'".$v : $v;
                },$row),',','"','');
            }
            fclose($stream);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public static function xlsxDownload(array $rows, string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP zip belum aktif. Gunakan CSV atau aktifkan ekstensi zip.');
        $out = tempnam(storage_path('app'),'tu_xlsx_');
        if ($out === false) throw new RuntimeException('Gagal menyiapkan file Excel sementara.');
        $zip = new ZipArchive();
        if ($zip->open($out,ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) throw new RuntimeException('Gagal membuat file XLSX.');
        try {
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data TU" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($rows as $i=>$row) {
                $xml .= '<row r="'.($i+1).'">';
                foreach (array_values($row) as $idx=>$value) {
                    $col = ''; $n = $idx+1;
                    while ($n > 0) { $n--; $col = chr(65 + ($n % 26)).$col; $n = intdiv($n,26); }
                    $xml .= '<c r="'.$col.($i+1).'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string)$value,ENT_QUOTES | ENT_XML1,'UTF-8').'</t></is></c>';
                }
                $xml .= '</row>';
            }
            $xml .= '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet1.xml',$xml);
        } finally { $zip->close(); }
        return response()->download($out,$filename,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
