<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\ImportGformLog;
use App\Models\Legalisir;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GoogleFormImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $navigationLabel = 'Impor Google Form (CSV)';

    protected static ?string $title = 'Impor Data dari Google Form / Spreadsheet';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.google-form-import';

    public ?array $data = [];

    public int $step = 1;
    public ?string $modul = 'surat_masuk';
    public ?string $filePath = null;
    public array $csvHeaders = [];
    public array $csvPreviewRows = [];
    public array $columnMapping = [];
    public array $importResult = [];

    public function mount(): void
    {
        $this->form->fill([
            'modul' => 'surat_masuk',
        ]);
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
                            ->options([
                                'surat_masuk' => 'Surat Masuk (Contoh: Formulir pengiriman surat dari instansi luar)',
                                'surat_keluar' => 'Surat Keluar (Contoh: Formulir permohonan surat keluar internal)',
                                'legalisir' => 'Layanan Legalisir (Contoh: Formulir permohonan legalisir alumni online)',
                            ])
                            ->default('surat_masuk')
                            ->required(),

                        FileUpload::make('file')
                            ->label('File CSV Respons Form')
                            ->disk('local')
                            ->directory('temp_imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->maxSize(5120)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function parseCsv(): void
    {
        $state = $this->form->getState();
        $this->modul = $state['modul'];
        $uploaded = $state['file'];

        if (! $uploaded) {
            Notification::make()->title('Silakan pilih file CSV terlebih dahulu.')->danger()->send();
            return;
        }

        $fullPath = Storage::disk('local')->path($uploaded);
        if (! file_exists($fullPath)) {
            Notification::make()->title('File tidak ditemukan di sistem.')->danger()->send();
            return;
        }

        $this->filePath = $uploaded;

        // Parse CSV headers and preview
        $file = fopen($fullPath, 'r');
        $headers = fgetcsv($file);

        if (! $headers) {
            fclose($file);
            Notification::make()->title('Format CSV kosong atau tidak valid.')->danger()->send();
            return;
        }

        // Clean headers from UTF-8 BOM if present
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
        $this->csvHeaders = array_map('trim', $headers);

        $preview = [];
        $count = 0;
        while (($row = fgetcsv($file)) !== false && $count < 5) {
            $preview[] = $row;
            $count++;
        }
        fclose($file);

        $this->csvPreviewRows = $preview;

        // Default smart mapping
        $this->columnMapping = $this->generateInitialMapping();
        $this->step = 2;
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
                'perihal' => 'Perihal / Isi Surat',
                'nomor_surat' => 'Nomor Surat Asal (Jika Ada)',
                'sifat' => 'Sifat Surat (Biasa/Penting)',
                'tautan_gform' => 'Tautan Berkas Drive / Dokumen',
            ];
        }

        if ($this->modul === 'surat_keluar') {
            return [
                'tanggal_surat' => 'Tanggal Surat / Timestamp',
                'tujuan' => 'Tujuan Surat / Instansi Tujuan',
                'perihal' => 'Perihal Surat',
                'pembuat_konsep' => 'Pemohon / Pembuat Konsep',
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
            'catatan' => 'Catatan Tambahan',
        ];
    }

    public function processImport(): void
    {
        if (! $this->filePath) {
            return;
        }

        $fullPath = Storage::disk('local')->path($this->filePath);
        $file = fopen($fullPath, 'r');
        $headers = fgetcsv($file);
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
        $headerIndices = array_flip(array_map('trim', $headers));

        $total = 0;
        $success = 0;
        $failed = 0;
        $errors = [];

        while (($row = fgetcsv($file)) !== false) {
            $total++;

            // Extract mapped fields
            $rowValues = [];
            foreach ($this->columnMapping as $fieldKey => $headerName) {
                if ($headerName && isset($headerIndices[$headerName])) {
                    $idx = $headerIndices[$headerName];
                    $rowValues[$fieldKey] = trim($row[$idx] ?? '');
                } else {
                    $rowValues[$fieldKey] = null;
                }
            }

            try {
                if ($this->modul === 'surat_masuk') {
                    if (empty($rowValues['pengirim']) || empty($rowValues['perihal'])) {
                        throw new \Exception('Kolom pengirim atau perihal tidak boleh kosong');
                    }

                    // Deteksi duplikat berdasarkan pengirim & perihal
                    $duplicate = SuratMasuk::where('pengirim', $rowValues['pengirim'])
                        ->where('perihal', $rowValues['perihal'])
                        ->exists();

                    if ($duplicate) {
                        $failed++;
                        $errors[] = "Baris {$total}: Duplikat terdeteksi (Pengirim: {$rowValues['pengirim']})";
                        continue;
                    }

                    SuratMasuk::create([
                        'nomor_agenda' => SuratMasuk::generateNomorAgenda(),
                        'nomor_surat' => $rowValues['nomor_surat'] ?: null,
                        'tanggal_surat' => now(),
                        'tanggal_diterima' => $rowValues['tanggal_diterima'] ? date('Y-m-d', strtotime($rowValues['tanggal_diterima'])) : now(),
                        'pengirim' => $rowValues['pengirim'],
                        'perihal' => $rowValues['perihal'],
                        'sifat' => in_array(strtolower($rowValues['sifat'] ?? ''), ['penting', 'segera', 'rahasia']) ? strtolower($rowValues['sifat']) : 'biasa',
                        'tautan_gform' => $rowValues['tautan_gform'] ?: null,
                        'sumber' => 'gform_import',
                        'status' => 'baru',
                        'created_by' => Auth::id(),
                    ]);
                    $success++;

                } elseif ($this->modul === 'surat_keluar') {
                    if (empty($rowValues['tujuan']) || empty($rowValues['perihal'])) {
                        throw new \Exception('Kolom tujuan atau perihal tidak boleh kosong');
                    }

                    $nomorData = SuratKeluar::generateNomorSurat();

                    SuratKeluar::create([
                        'nomor_surat' => $nomorData['nomor_surat'],
                        'nomor_urut' => $nomorData['nomor_urut'],
                        'tahun' => $nomorData['tahun'],
                        'tanggal_surat' => $rowValues['tanggal_surat'] ? date('Y-m-d', strtotime($rowValues['tanggal_surat'])) : now(),
                        'tujuan' => $rowValues['tujuan'],
                        'perihal' => $rowValues['perihal'],
                        'pembuat_konsep' => $rowValues['pembuat_konsep'] ?: 'Google Form Import',
                        'tautan_gform' => $rowValues['tautan_gform'] ?: null,
                        'sumber' => 'gform_import',
                        'status' => 'draf',
                        'created_by' => Auth::id(),
                    ]);
                    $success++;

                } elseif ($this->modul === 'legalisir') {
                    if (empty($rowValues['nama_pemohon'])) {
                        throw new \Exception('Nama pemohon tidak boleh kosong');
                    }

                    // Deteksi duplikat
                    $duplicate = Legalisir::where('nama_pemohon', $rowValues['nama_pemohon'])
                        ->where('jenis_dokumen', $rowValues['jenis_dokumen'] ?: 'Ijazah')
                        ->whereDate('tanggal_permohonan', now())
                        ->exists();

                    if ($duplicate) {
                        $failed++;
                        $errors[] = "Baris {$total}: Duplikat permohonan legalisir untuk nama {$rowValues['nama_pemohon']}";
                        continue;
                    }

                    Legalisir::create([
                        'nomor_legalisir' => Legalisir::generateNomorLegalisir(),
                        'nama_pemohon' => $rowValues['nama_pemohon'],
                        'kontak' => $rowValues['kontak'] ?: null,
                        'status_pemohon' => in_array(strtolower($rowValues['status_pemohon'] ?? ''), ['alumni', 'siswa', 'wali']) ? strtolower($rowValues['status_pemohon']) : 'alumni',
                        'jenis_dokumen' => $rowValues['jenis_dokumen'] ?: 'Ijazah',
                        'jumlah_lembar' => is_numeric($rowValues['jumlah_lembar'] ?? '') ? (int) $rowValues['jumlah_lembar'] : 1,
                        'tanggal_permohonan' => $rowValues['tanggal_permohonan'] ? date('Y-m-d', strtotime($rowValues['tanggal_permohonan'])) : now(),
                        'catatan' => 'Diimpor otomatis dari Google Form respons: ' . ($rowValues['catatan'] ?? ''),
                        'status' => 'diterima',
                        'petugas_id' => Auth::id(),
                    ]);
                    $success++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Baris {$total}: " . $e->getMessage();
            }
        }

        fclose($file);

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
            ['file' => $this->filePath, 'total' => $total, 'berhasil' => $success, 'gagal' => $failed]
        );

        $this->importResult = [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];

        $this->step = 3;

        Notification::make()
            ->title("Impor Selesai: {$success} berhasil, {$failed} gagal.")
            ->success()
            ->send();
    }

    public function resetImport(): void
    {
        $this->step = 1;
        $this->filePath = null;
        $this->csvHeaders = [];
        $this->csvPreviewRows = [];
        $this->columnMapping = [];
        $this->importResult = [];
        $this->form->fill(['modul' => 'surat_masuk']);
    }
}
