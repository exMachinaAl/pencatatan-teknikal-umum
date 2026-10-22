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
                'columns' => ['nip', 'nama', 'status_kepegawaian', 'golongan_kode'],
                'required' => ['nama', 'status_kepegawaian'],
                'dates' => [],
                'lengths' => ['nip' => 30, 'nama' => 150, 'golongan_kode' => 20],
                'enum' => ['status_kepegawaian' => Pegawai::STATUSES],
            ],
            'kp' => [
                'columns' => ['pegawai_nip', 'pegawai_nama', 'jenis', 'golongan_lama_kode', 'golongan_baru_kode', 'mk_tahun', 'mk_bulan', 'tautan_lampiran'],
                'required' => ['jenis', 'golongan_baru_kode', 'mk_tahun', 'mk_bulan'], 'dates' => [],
                'lengths' => ['pegawai_nip' => 30, 'pegawai_nama' => 150, 'golongan_lama_kode' => 20, 'golongan_baru_kode' => 20, 'tautan_lampiran' => 500],
                'enum' => ['jenis' => array_keys(KenaikanPangkat::JENIS_KP)],
            ],
            'kgb' => [
                'columns' => ['pegawai_nip', 'pegawai_nama', 'golongan_kode', 'mk_tahun', 'mk_bulan', 'tautan_lampiran'],
                'required' => ['golongan_kode', 'mk_tahun', 'mk_bulan'], 'dates' => [],
                'lengths' => ['pegawai_nip' => 30, 'pegawai_nama' => 150, 'golongan_kode' => 20, 'tautan_lampiran' => 500],
                'enum' => [],
            ],
            default => throw new RuntimeException('Modul kepegawaian tidak tersedia.'),
        };
    }

    public static function template(string $module): array
    {
        $schema = self::schema($module);
        $examples = [
            'pegawai' => ['198001012006041001', 'Contoh Pegawai, S.Pd', 'PNS', 'III/a'],
            'kp' => ['198001012006041001', '', 'reguler', 'III/a', 'III/b', '8', '0', 'https://drive.google.com/file/d/contoh/view'],
            'kgb' => ['198001012006041001', '', 'III/a', '8', '0', 'https://docs.google.com/spreadsheets/d/contoh/edit'],
        ];

        return [$schema['columns'], $examples[$module]];
    }

    private static function cleanDate(string $value, string $field): string
    {
        // XLSX sering mengubah tanggal menjadi serial Excel.
        if (ctype_digit($value) && (int) $value > 25000 && (int) $value < 100000) {
            $value = (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $d || $d->format('Y-m-d') !== $value) {
            throw new RuntimeException($field.' harus YYYY-MM-DD.');
        }

        return $value;
    }

    private static function normalize(string $module, array $input): array
    {
        $cfg = self::schema($module);
        $out = [];
        foreach ($cfg['columns'] as $name) {
            $v = trim((string) ($input[$name] ?? ''));
            if ($v === '' || $v === null) {
                $out[$name] = null;

                continue;
            }
            if (str_contains($v, "\0") || preg_match('/[\x01-\x08\x0B\x0C\x0E-\x1F]/', $v)) {
                throw new RuntimeException($name.' mengandung karakter tidak sah.');
            }
            if (isset($cfg['lengths'][$name]) && mb_strlen($v, 'UTF-8') > $cfg['lengths'][$name]) {
                throw new RuntimeException($name.' melebihi batas panjang.');
            }
            if (in_array($name, $cfg['dates'], true)) {
                $v = self::cleanDate($v, $name);
            }
            if (isset($cfg['enum'][$name]) && ! in_array($v, $cfg['enum'][$name], true)) {
                throw new RuntimeException($name.' tidak sesuai pilihan.');
            }
            $out[$name] = $v;
        }
        foreach ($cfg['required'] as $required) {
            if ($out[$required] === null) {
                throw new RuntimeException($required.' wajib diisi.');
            }
        }
        if ($module === 'pegawai') {
            if ($out['nip'] !== null && ! preg_match('/^[A-Za-z0-9.\/-]{1,30}$/D', $out['nip'])) {
                throw new RuntimeException('Format NIP tidak sah.');
            }
            if ($out['golongan_kode']) {
                self::golonganId($out['golongan_kode']);
            }
        } else {
            if (! $out['pegawai_nip'] && ! $out['pegawai_nama']) {
                throw new RuntimeException('Isi pegawai_nip atau pegawai_nama.');
            }
            $pegawai = self::resolvePegawai($out);
            $out['_pegawai_id'] = $pegawai->id;
            $codes = $module === 'kp' ? ['golongan_lama_kode', 'golongan_baru_kode'] : ['golongan_kode'];
            foreach ($codes as $code) {
                if ($out[$code]) {
                    self::golonganId($out[$code]);
                }
            }
            if ($module === 'kp') {
                if ($out['golongan_lama_kode'] && $out['golongan_baru_kode'] && $out['golongan_lama_kode'] === $out['golongan_baru_kode']) {
                    throw new RuntimeException('Golongan baru harus berbeda dari golongan lama.');
                }
            }
            foreach (['mk_tahun' => 60, 'mk_bulan' => 11] as $key => $max) {
                $value = $out[$key] ?? '0';
                if (! ctype_digit((string) $value) || (int) $value > $max) {
                    throw new RuntimeException($key.' harus 0-'.$max.'.');
                }
                $out[$key] = (int) $value;
            }
            if ($out['tautan_lampiran'] !== null
                && (filter_var($out['tautan_lampiran'], FILTER_VALIDATE_URL) === false
                    || strtolower((string) parse_url($out['tautan_lampiran'], PHP_URL_SCHEME)) !== 'https')) {
                throw new RuntimeException('tautan_lampiran harus berupa tautan HTTPS yang valid.');
            }
        }

        return $out;
    }

    private static function golonganId(?string $code): ?int
    {
        if (! $code) {
            return null;
        }
        $id = RefGolongan::where('kode', $code)->value('id');
        if (! $id) {
            throw new RuntimeException('Kode golongan tidak ditemukan: '.$code);
        }

        return (int) $id;
    }

    private static function resolvePegawai(array $row): Pegawai
    {
        if ($row['pegawai_nip']) {
            $q = Pegawai::where('nip', $row['pegawai_nip']);
            $count = (clone $q)->count();
            if ($count !== 1) {
                throw new RuntimeException('NIP pegawai tidak ditemukan atau tidak unik: '.$row['pegawai_nip']);
            }
            $pegawai = $q->firstOrFail();
            if ($row['pegawai_nama'] && mb_strtolower($pegawai->nama) !== mb_strtolower($row['pegawai_nama'])) {
                throw new RuntimeException('Nama pegawai tidak cocok dengan NIP.');
            }

            return $pegawai;
        }
        $q = Pegawai::where('nama', $row['pegawai_nama']);
        if ((clone $q)->count() !== 1) {
            throw new RuntimeException('Nama pegawai tidak ditemukan/ambigu. Gunakan NIP.');
        }

        return $q->firstOrFail();
    }

    private static function exists(string $module, array $row): bool
    {
        return match ($module) {
            'pegawai' => ($row['nip'] ? Pegawai::withTrashed()->where('nip', $row['nip'])->exists() : Pegawai::withTrashed()->where('nama', $row['nama'])->exists()),
            'kp' => KenaikanPangkat::withTrashed()->where('integrity_key', KenaikanPangkat::integrityKey(
                (int) $row['_pegawai_id'],
                (string) $row['jenis'],
                null,
                (int) $row['mk_tahun'],
                (int) $row['mk_bulan'],
            ))->exists(),
            'kgb' => GajiBerkala::withTrashed()->where('integrity_key', GajiBerkala::integrityKey(
                (int) $row['_pegawai_id'],
                null,
                self::golonganId($row['golongan_kode']),
                (int) $row['mk_tahun'],
                (int) $row['mk_bulan'],
            ))->exists(),
            default => throw new RuntimeException('Modul salah.'),
        };
    }

    private static function identity(string $module, array $row): string
    {
        return match ($module) {
            'pegawai' => 'P|'.($row['nip'] ?: mb_strtolower($row['nama'])),
            'kp' => 'KP|'.$row['_pegawai_id'].'|'.$row['jenis'].'|'.$row['mk_tahun'].'|'.$row['mk_bulan'],
            'kgb' => 'KGB|'.$row['_pegawai_id'].'|'.$row['golongan_kode'].'|'.$row['mk_tahun'].'|'.$row['mk_bulan'],
        };
    }

    public static function analyze(string $module, string $path): array
    {
        $cfg = self::schema($module);
        $file = TuDataExchange::fileRows($path);
        if (count($file) < 2) {
            throw new RuntimeException('File tidak memiliki baris data.');
        }
        $head = array_map(static fn ($v) => strtolower(trim((string) $v)), $file[0]);
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
        if (count($head) !== count(array_unique($head))) {
            throw new RuntimeException('Nama header berulang.');
        }
        $unknownColumns = array_values(array_diff(array_filter($head), $cfg['columns']));
        if ($unknownColumns !== []) {
            throw new RuntimeException('Kolom tidak didukung oleh versi terbaru: '.implode(', ', $unknownColumns).'. Unduh template terbaru dari aplikasi.');
        }
        foreach ($cfg['required'] as $col) {
            if (! in_array($col, $head, true)) {
                throw new RuntimeException('Header wajib hilang: '.$col);
            }
        }
        foreach ($file as $i => $line) {
            if ($i === 0) {
                continue;
            }
            if (count($line) > count($head) && array_filter(array_slice($line, count($head)), static fn ($v) => trim((string) $v) !== '')) {
                throw new RuntimeException('Baris '.($i + 1).': jumlah kolom berlebih.');
            }
        }
        $valid = [];
        $duplicate = [];
        $errors = [];
        $seen = [];
        $total = 0;
        $sample = [];
        foreach (array_slice($file, 1) as $idx => $line) {
            if (! array_filter($line, static fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $total++;
            $number = $idx + 2;
            $input = [];
            foreach ($head as $i => $name) {
                if ($name !== '') {
                    $input[$name] = $line[$i] ?? '';
                }
            }
            try {
                $row = self::normalize($module, $input);
                $identity = self::identity($module, $row);
                if (isset($seen[$identity]) || self::exists($module, $row)) {
                    $duplicate[] = 'Baris '.$number.': duplikat dilewati';

                    continue;
                }
                $seen[$identity] = true;
                $valid[] = $row;
                if (count($sample) < 5) {
                    $s = [];
                    foreach ($cfg['columns'] as $col) {
                        $s[] = $module === 'kgb' && str_starts_with($col, 'gaji_pokok') ? '[nominal disembunyikan]' : ($row[$col] ?? '');
                    }
                    $sample[] = $s;
                }
            } catch (RuntimeException $e) {
                $errors[] = 'Baris '.$number.': '.$e->getMessage();
            }
        }
        if ($total === 0) {
            throw new RuntimeException('Tidak ada baris untuk diimpor.');
        }

        return ['total' => $total, 'valid' => count($valid), 'duplicates' => count($duplicate), 'invalid' => count($errors),
            'errors' => array_slice($errors, 0, 30), 'duplicate_messages' => array_slice($duplicate, 0, 10),
            'columns' => $cfg['columns'], 'preview' => $sample, 'rows' => $valid];
    }

    public static function import(string $module, string $path, int $userId): array
    {
        return DB::transaction(function () use ($module, $path, $userId) {
            $check = self::analyze($module, $path);
            if ($check['invalid'] > 0) {
                throw new RuntimeException('Ada baris invalid; tidak ada perubahan disimpan.');
            }
            $count = 0;
            foreach ($check['rows'] as $row) {
                if ($module !== 'pegawai') {
                    Pegawai::whereKey($row['_pegawai_id'])->lockForUpdate()->firstOrFail();
                }
                if (self::exists($module, $row)) {
                    continue;
                }
                if ($module === 'pegawai') {
                    Pegawai::create([
                        'nip' => $row['nip'], 'nama' => $row['nama'], 'status_kepegawaian' => $row['status_kepegawaian'],
                        'golongan_id' => self::golonganId($row['golongan_kode']),
                    ]);
                } elseif ($module === 'kp') {
                    KenaikanPangkat::create([
                        'pegawai_id' => $row['_pegawai_id'], 'jenis' => $row['jenis'],
                        'golongan_lama_id' => self::golonganId($row['golongan_lama_kode']),
                        'golongan_baru_id' => self::golonganId($row['golongan_baru_kode']),
                        'mk_tahun' => $row['mk_tahun'], 'mk_bulan' => $row['mk_bulan'],
                        'tautan_lampiran' => $row['tautan_lampiran'], 'created_by' => $userId,
                    ]);
                } else {
                    GajiBerkala::create([
                        'pegawai_id' => $row['_pegawai_id'], 'golongan_id' => self::golonganId($row['golongan_kode']),
                        'mk_tahun' => $row['mk_tahun'], 'mk_bulan' => $row['mk_bulan'],
                        'tautan_lampiran' => $row['tautan_lampiran'],
                        'created_by' => $userId,
                    ]);
                }
                $count++;
            }

            return ['success' => $count, 'skipped' => $check['total'] - $count, 'total' => $check['total']];
        }, 3);
    }

    public static function export(string $module, ?string $needle, ?string $start, ?string $end): array
    {
        $cfg = self::schema($module);
        $needle = trim((string) $needle);
        if (mb_strlen($needle, 'UTF-8') > self::MAX_SEARCH) {
            throw new RuntimeException('Pencarian maksimal 120 karakter.');
        }
        foreach ([$start, $end] as $date) {
            if ($date) {
                self::cleanDate($date, 'tanggal filter');
            }
        }
        if ($start && $end && $start > $end) {
            throw new RuntimeException('Tanggal awal harus sebelum akhir.');
        }
        $query = match ($module) {
            'pegawai' => Pegawai::query()->with('golongan'),
            'kp' => KenaikanPangkat::query()->with(['pegawai', 'golonganLama', 'golonganBaru']),
            'kgb' => GajiBerkala::query()->with(['pegawai', 'golongan']),
        };
        if ($needle !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';
            if ($module === 'pegawai') {
                $query->where(fn ($q) => $q->where('nama', 'like', $like)->orWhere('nip', 'like', $like));
            } elseif ($module === 'kp') {
                $query->whereHas('pegawai', fn ($p) => $p->where('nama', 'like', $like)->orWhere('nip', 'like', $like));
            } else {
                $query->whereHas('pegawai', fn ($p) => $p->where('nama', 'like', $like)->orWhere('nip', 'like', $like));
            }
        }
        $dateColumn = 'created_at';
        if ($start) {
            $query->whereDate($dateColumn, '>=', $start);
        }
        if ($end) {
            $query->whereDate($dateColumn, '<=', $end);
        }
        if ((clone $query)->count() > TuDataExchange::MAX_EXPORT) {
            throw new RuntimeException('Ekspor lebih dari 10.000 baris, gunakan filter.');
        }
        $rows = [$cfg['columns']];
        $query->chunkById(500, function ($items) use (&$rows, $module, $cfg) {
            foreach ($items as $item) {
                if ($module === 'pegawai') {
                    $data = [
                        'nip' => $item->nip, 'nama' => $item->nama, 'status_kepegawaian' => $item->status_kepegawaian,
                        'golongan_kode' => $item->golongan?->kode,
                    ];
                } elseif ($module === 'kp') {
                    $data = [
                        'pegawai_nip' => $item->pegawai?->nip, 'pegawai_nama' => $item->pegawai?->nama, 'jenis' => $item->jenis,
                        'golongan_lama_kode' => $item->golonganLama?->kode, 'golongan_baru_kode' => $item->golonganBaru?->kode,
                        'mk_tahun' => $item->mk_tahun, 'mk_bulan' => $item->mk_bulan,
                        'tautan_lampiran' => $item->tautan_lampiran,
                    ];
                } else {
                    $data = [
                        'pegawai_nip' => $item->pegawai?->nip, 'pegawai_nama' => $item->pegawai?->nama,
                        'golongan_kode' => $item->golongan?->kode,
                        'mk_tahun' => $item->mk_tahun, 'mk_bulan' => $item->mk_bulan,
                        'tautan_lampiran' => $item->tautan_lampiran,
                    ];
                }
                $rows[] = array_map(static function ($key) use ($data) {
                    $v = $data[$key] ?? null;

                    return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) ($v ?? '');
                }, $cfg['columns']);
            }
        });

        return $rows;
    }
}
