<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\ImportGformLog;
use App\Models\Legalisir;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GoogleFormImport extends Page implements HasForms
{
    use InteractsWithForms;

    private const MAX_IMPORT_ROWS = 1000;

    private const MAX_FILE_BYTES = 5 * 1024 * 1024;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $navigationLabel = 'Impor Google Form (CSV)';

    protected static ?string $title = 'Impor Data dari Google Form / Spreadsheet';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.google-form-import';

    public ?array $data = [];

    public int $step = 1;

    public ?string $modul = null;

    public ?string $filePath = null;

    public ?string $fileHash = null;

    public ?string $fileProof = null;

    public array $csvHeaders = [];

    public array $csvPreviewRows = [];

    public array $columnMapping = [];

    public array $importResult = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->modul = array_key_first($this->allowedModules());

        $this->form->fill([
            'modul' => $this->modul,
        ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (
            $user->canPerform('surat', 'import') ||
            $user->canPerform('legalisir', 'import')
        );
    }

    protected function allowedModules(): array
    {
        $options = [];
        $user = auth()->user();

        if ($user?->canPerform('surat', 'import')) {
            $options['surat_masuk'] = 'Surat Masuk';
            $options['surat_keluar'] = 'Surat Keluar';
        }

        if ($user?->canPerform('legalisir', 'import')) {
            $options['legalisir'] = 'Layanan Legalisir';
        }

        return $options;
    }

    protected function authorizeModule(?string $module): void
    {
        abort_unless(array_key_exists((string) $module, $this->allowedModules()), 403);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Langkah 1: Pilih Modul & Unggah Berkas Respons Form')
                    ->description('Unggah file CSV yang diekspor dari Google Spreadsheet hasil respons Google Form')
                    ->schema([
                        Radio::make('modul')
                            ->label('Target Modul Sistem')
                            ->options(fn (): array => $this->allowedModules())
                            ->default(fn (): ?string => array_key_first($this->allowedModules()))
                            ->required(),

                        FileUpload::make('file')
                            ->label('File CSV Respons Form')
                            ->disk('local')
                            ->directory(fn (): string => $this->temporaryDirectory())
                            ->visibility('private')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->maxSize(5120)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    private function temporaryDirectory(): string
    {
        return 'temp_imports/'.Auth::id();
    }

    private function secureUploadPath(string $path): string
    {
        $directory = $this->temporaryDirectory();

        if (
            ! str_starts_with($path, $directory.'/') ||
            str_contains($path, '..') ||
            str_contains($path, '\\')
        ) {
            throw new RuntimeException('Lokasi berkas impor tidak sah.');
        }

        $fullPath = Storage::disk('local')->path($path);
        $realPath = realpath($fullPath);
        $rootPath = realpath(Storage::disk('local')->path($directory));

        if (! $realPath || ! $rootPath || ! str_starts_with($realPath, $rootPath.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Berkas impor tidak ditemukan di penyimpanan privat pengguna.');
        }

        if (strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) !== 'csv') {
            throw new RuntimeException('Berkas impor harus menggunakan ekstensi .csv.');
        }

        if (filesize($realPath) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('Ukuran CSV melebihi batas 5 MB.');
        }

        return $realPath;
    }

    private function proof(): string
    {
        return hash_hmac('sha256', implode('|', [
            Auth::id(),
            $this->modul,
            $this->filePath,
            $this->fileHash,
        ]), (string) config('app.key'));
    }

    public function parseCsv(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $this->modul = $state['modul'];
        $this->authorizeModule($this->modul);
        $uploaded = $state['file'];

        if (! $uploaded) {
            Notification::make()->title('Silakan pilih file CSV terlebih dahulu.')->danger()->send();

            return;
        }

        try {
            $fullPath = $this->secureUploadPath($uploaded);
            $file = fopen($fullPath, 'rb');

            if ($file === false) {
                throw new RuntimeException('CSV tidak dapat dibuka.');
            }

            try {
                $headers = fgetcsv($file);
                if (! is_array($headers) || $headers === []) {
                    throw new RuntimeException('Format CSV kosong atau tidak valid.');
                }

                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
                $headers = array_map(fn ($header): string => trim((string) $header), $headers);

                if (in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
                    throw new RuntimeException('Nama kolom CSV tidak boleh kosong atau duplikat.');
                }

                $preview = [];
                while (($row = fgetcsv($file)) !== false && count($preview) < 5) {
                    if ($this->rowIsEmpty($row)) {
                        continue;
                    }

                    $preview[] = array_slice(array_pad($row, count($headers), ''), 0, count($headers));
                }
            } finally {
                fclose($file);
            }

            $this->filePath = $uploaded;
            $this->fileHash = hash_file('sha256', $fullPath);
            $this->fileProof = $this->proof();
            $this->csvHeaders = $headers;
            $this->csvPreviewRows = $preview;
            $this->columnMapping = $this->generateInitialMapping();
            $this->step = 2;
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    private function rowIsEmpty(array $row): bool
    {
        return count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) === 0;
    }

    protected function generateInitialMapping(): array
    {
        $mapping = [];
        $targetFields = $this->getTargetFields();

        foreach ($targetFields as $key => $label) {
            $mapping[$key] = '';
            foreach ($this->csvHeaders as $header) {
                $headerLower = strtolower($header);
                $keyLower = strtolower($key);
                if (str_contains($headerLower, $keyLower) || str_contains($headerLower, strtolower($label))) {
                    $mapping[$key] = $header;
                    break;
                }
            }
        }

        return $mapping;
    }

    public function getTargetFields(): array
    {
        if ($this->modul === 'surat_masuk') {
            return [
                'tanggal_diterima' => 'Tanggal Masuk / Timestamp Form',
                'pengirim' => 'Nama Pengirim / Instansi Asal',
                'nomor_surat' => 'Nomor Surat Asal (Jika Ada)',
                'kode_klasifikasi' => 'Kode Klasifikasi Surat',
                'lokasi_arsip_fisik' => 'Lokasi Arsip Fisik',
                'tautan_gform' => 'Tautan Berkas Drive / Dokumen',
            ];
        }

        if ($this->modul === 'surat_keluar') {
            return [
                'nomor_surat' => 'Nomor Surat Resmi',
                'tanggal_surat' => 'Tanggal Surat / Timestamp',
                'tujuan' => 'Tujuan Surat / Instansi Tujuan',
                'kode_klasifikasi' => 'Kode Klasifikasi Surat',
                'lokasi_arsip_fisik' => 'Lokasi Arsip Fisik',
                'tautan_gform' => 'Tautan Draf File Drive',
            ];
        }

        // Legalisir
        return [
            'tanggal_permohonan' => 'Tanggal Permohonan / Timestamp',
            'nama_pemohon' => 'Nama Lengkap Pemohon',
            'kontak' => 'No HP / WhatsApp',
            'status_pemohon' => 'Status (Alumni/Siswa/Wali)',
            'jenis_dokumen' => 'Jenis Dokumen (Ijazah/SKHUN)',
            'jumlah_lembar' => 'Jumlah Lembar',
            'lokasi_arsip_fisik' => 'Lokasi Arsip Fisik / Kode Penyimpanan',
            'catatan' => 'Catatan Tambahan',
        ];
    }

    private function cleanText(mixed $value, string $field, int $maxLength, bool $required = false): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            if ($required) {
                throw new RuntimeException("{$field} wajib diisi.");
            }

            return null;
        }

        if (str_contains($value, "\0") || preg_match('/[\x01-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new RuntimeException("{$field} mengandung karakter yang tidak diperbolehkan.");
        }

        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new RuntimeException("{$field} melebihi batas {$maxLength} karakter.");
        }

        return $value;
    }

    private function cleanDate(mixed $value, string $field): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return now()->toDateString();
        }

        $formats = [
            'Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i',
            'd/m/Y', 'd/m/Y H:i:s', 'd/m/Y H:i',
            'd-m-Y', 'd-m-Y H:i:s', 'd-m-Y H:i',
            'm/d/Y', 'm/d/Y H:i:s', 'm/d/Y h:i:s A',
        ];

        foreach ($formats as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        throw new RuntimeException("{$field} tidak valid. Gunakan tanggal yang jelas, misalnya 2026-10-19.");
    }

    private function cleanUrl(mixed $value): ?string
    {
        $url = $this->cleanText($value, 'Tautan dokumen', 500);
        if ($url === null) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Tautan dokumen harus berupa URL HTTP/HTTPS yang valid.');
        }

        return $url;
    }

    private function mappedRow(array $row, array $headerIndices): array
    {
        $values = [];

        foreach (array_keys($this->getTargetFields()) as $fieldKey) {
            $headerName = $this->columnMapping[$fieldKey] ?? null;
            $index = is_string($headerName) ? ($headerIndices[$headerName] ?? null) : null;
            $values[$fieldKey] = $index !== null ? trim((string) ($row[$index] ?? '')) : null;
        }

        return $values;
    }

    private function importRow(array $rowValues): void
    {
        DB::transaction(function () use ($rowValues): void {
            if ($this->modul === 'surat_masuk') {
                $pengirim = $this->cleanText($rowValues['pengirim'], 'Pengirim', 200, true);
                $nomorSurat = $this->cleanText($rowValues['nomor_surat'], 'Nomor surat', 100);
                $tanggalDiterima = $this->cleanDate($rowValues['tanggal_diterima'], 'Tanggal diterima');
                $kodeKlasifikasi = $this->cleanText($rowValues['kode_klasifikasi'], 'Kode klasifikasi', 30);

                $duplicate = SuratMasuk::withTrashed()
                    ->where('pengirim', $pengirim)
                    ->when($nomorSurat, fn ($query) => $query->where('nomor_surat', $nomorSurat))
                    ->whereDate('tanggal_diterima', $tanggalDiterima)
                    ->exists();

                if ($duplicate) {
                    throw new RuntimeException("Duplikat surat masuk dari {$pengirim}.");
                }

                SuratMasuk::create([
                    'nomor_agenda' => SuratMasuk::generateNomorAgenda((int) substr($tanggalDiterima, 0, 4)),
                    'nomor_surat' => $nomorSurat,
                    'tanggal_surat' => $tanggalDiterima,
                    'tanggal_diterima' => $tanggalDiterima,
                    'pengirim' => $pengirim,
                    'klasifikasi_id' => $kodeKlasifikasi === null ? null : RefKlasifikasiSurat::firstOrCreate(
                        ['kode' => $kodeKlasifikasi],
                        ['nama' => $kodeKlasifikasi],
                    )->getKey(),
                    'lokasi_arsip_fisik' => $this->cleanText($rowValues['lokasi_arsip_fisik'], 'Lokasi arsip fisik', 150),
                    'tautan_gform' => $this->cleanUrl($rowValues['tautan_gform']),
                    'sumber' => 'gform_import',
                    'created_by' => Auth::id(),
                ]);

                return;
            }

            if ($this->modul === 'surat_keluar') {
                $nomorSurat = $this->cleanText($rowValues['nomor_surat'], 'Nomor surat resmi', 100, true);
                $tujuan = $this->cleanText($rowValues['tujuan'], 'Tujuan', 200, true);
                $tanggalSurat = $this->cleanDate($rowValues['tanggal_surat'], 'Tanggal surat');
                $kodeKlasifikasi = $this->cleanText($rowValues['kode_klasifikasi'], 'Kode klasifikasi', 30);

                if (SuratKeluar::withTrashed()->where('nomor_surat', $nomorSurat)->exists()) {
                    throw new RuntimeException("Nomor surat keluar {$nomorSurat} sudah tercatat.");
                }

                SuratKeluar::create([
                    'nomor_surat' => $nomorSurat,
                    'tanggal_surat' => $tanggalSurat,
                    'tujuan' => $tujuan,
                    'klasifikasi_id' => $kodeKlasifikasi === null ? null : RefKlasifikasiSurat::firstOrCreate(
                        ['kode' => $kodeKlasifikasi],
                        ['nama' => $kodeKlasifikasi],
                    )->getKey(),
                    'lokasi_arsip_fisik' => $this->cleanText($rowValues['lokasi_arsip_fisik'], 'Lokasi arsip fisik', 150),
                    'tautan_gform' => $this->cleanUrl($rowValues['tautan_gform']),
                    'sumber' => 'gform_import',
                    'created_by' => Auth::id(),
                ]);

                return;
            }

            if ($this->modul !== 'legalisir') {
                throw new RuntimeException('Modul impor tidak dikenali.');
            }

            $nama = $this->cleanText($rowValues['nama_pemohon'], 'Nama pemohon', 150, true);
            $jenisDokumen = $this->cleanText($rowValues['jenis_dokumen'], 'Jenis dokumen', 100) ?? 'Ijazah';
            $tanggalPermohonan = $this->cleanDate($rowValues['tanggal_permohonan'], 'Tanggal permohonan');

            if (Legalisir::withTrashed()->where('nama_pemohon', $nama)
                ->where('jenis_dokumen', $jenisDokumen)
                ->whereDate('tanggal_permohonan', $tanggalPermohonan)->exists()) {
                throw new RuntimeException("Duplikat permohonan legalisir untuk {$nama}.");
            }

            $jumlahLembar = trim((string) ($rowValues['jumlah_lembar'] ?? ''));
            if ($jumlahLembar === '') {
                $jumlahLembar = 1;
            } elseif (! ctype_digit($jumlahLembar) || (int) $jumlahLembar < 1 || (int) $jumlahLembar > 100) {
                throw new RuntimeException('Jumlah lembar harus berupa angka 1 sampai 100.');
            }

            $statusPemohon = strtolower((string) ($rowValues['status_pemohon'] ?? ''));
            Legalisir::create([
                'nomor_legalisir' => Legalisir::generateNomorLegalisir((int) substr($tanggalPermohonan, 0, 4)),
                'nama_pemohon' => $nama,
                'kontak' => $this->cleanText($rowValues['kontak'], 'Kontak', 60),
                'status_pemohon' => in_array($statusPemohon, ['alumni', 'siswa', 'wali', 'lainnya'], true) ? $statusPemohon : 'alumni',
                'jenis_dokumen' => $jenisDokumen,
                'jumlah_lembar' => (int) $jumlahLembar,
                'lokasi_arsip_fisik' => $this->cleanText($rowValues['lokasi_arsip_fisik'], 'Lokasi arsip fisik', 150),
                'tanggal_permohonan' => $tanggalPermohonan,
                'catatan' => $this->cleanText($rowValues['catatan'], 'Catatan', 20000),
                'status' => 'diterima',
                'petugas_id' => Auth::id(),
            ]);
        });
    }

    public function processImport(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->authorizeModule($this->modul);

        if (! $this->filePath || ! $this->fileHash || ! $this->fileProof) {
            Notification::make()->title('Pratinjau CSV tidak valid. Unggah ulang berkas.')->danger()->send();

            return;
        }

        try {
            if (! hash_equals($this->proof(), $this->fileProof)) {
                throw new RuntimeException('Konfirmasi impor tidak cocok. Unggah ulang berkas.');
            }

            $fullPath = $this->secureUploadPath($this->filePath);
            if (! hash_equals($this->fileHash, hash_file('sha256', $fullPath))) {
                throw new RuntimeException('Isi CSV berubah setelah pratinjau. Unggah ulang berkas.');
            }

            $file = fopen($fullPath, 'rb');
            if ($file === false) {
                throw new RuntimeException('CSV tidak dapat dibuka.');
            }

            try {
                $headers = fgetcsv($file);
                if (! is_array($headers) || $headers === []) {
                    throw new RuntimeException('Header CSV tidak tersedia.');
                }
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
                $headers = array_map(fn ($header): string => trim((string) $header), $headers);
                if ($headers !== $this->csvHeaders) {
                    throw new RuntimeException('Header CSV berubah setelah pratinjau.');
                }

                $rows = [];
                while (($row = fgetcsv($file)) !== false) {
                    if ($this->rowIsEmpty($row)) {
                        continue;
                    }
                    if (count($rows) >= self::MAX_IMPORT_ROWS) {
                        throw new RuntimeException('CSV melebihi batas 1.000 baris data.');
                    }
                    $rows[] = $row;
                }
            } finally {
                fclose($file);
            }
        } catch (RuntimeException $e) {
            Notification::make()->title('Impor dibatalkan')->body($e->getMessage())->danger()->send();

            return;
        }

        $headerIndices = array_flip($headers);

        $total = count($rows);
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $this->importRow($this->mappedRow($row, $headerIndices));
                $success++;
            } catch (RuntimeException $e) {
                $failed++;
                $errors[] = 'Baris '.($index + 2).': '.$e->getMessage();
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $errors[] = 'Baris '.($index + 2).': gagal diproses karena kesalahan sistem.';
            }
        }

        // Catat ke log import
        ImportGformLog::create([
            'modul' => $this->modul,
            'nama_file' => basename($this->filePath),
            'baris_total' => $total,
            'baris_berhasil' => $success,
            'baris_gagal' => $failed,
            'detail_error' => implode("\n", array_slice($errors, 0, 20)),
            'imported_by' => Auth::id(),
        ]);

        AuditLog::record(
            'import',
            $this->modul,
            null,
            null,
            ['file' => basename($this->filePath), 'total' => $total, 'berhasil' => $success, 'gagal' => $failed]
        );

        Storage::disk('local')->delete($this->filePath);
        $this->filePath = null;
        $this->fileHash = null;
        $this->fileProof = null;

        $this->importResult = [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];

        $this->step = 3;

        $notification = Notification::make()->title("Impor selesai: {$success} berhasil, {$failed} gagal.");
        ($failed > 0 ? $notification->warning() : $notification->success())->send();
    }

    public function resetImport(): void
    {
        if ($this->filePath && str_starts_with($this->filePath, $this->temporaryDirectory().'/')) {
            Storage::disk('local')->delete($this->filePath);
        }

        $this->step = 1;
        $this->filePath = null;
        $this->fileHash = null;
        $this->fileProof = null;
        $this->csvHeaders = [];
        $this->csvPreviewRows = [];
        $this->columnMapping = [];
        $this->importResult = [];
        $this->modul = array_key_first($this->allowedModules());
        $this->form->fill(['modul' => $this->modul]);
    }
}
