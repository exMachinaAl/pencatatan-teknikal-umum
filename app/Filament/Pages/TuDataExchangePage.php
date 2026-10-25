<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TuDataExchange;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Halaman mandiri: resource dan import Google Form lama tidak disentuh. */
class TuDataExchangePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $navigationLabel = 'Export & Import Data';

    protected static ?string $title = 'Export & Import Administrasi';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.tu-data-exchange';

    public ?array $data = [];

    public ?array $preview = null;

    public ?array $result = null;

    public ?string $previewPath = null;

    public ?string $previewModule = null;

    public ?string $previewHash = null;

    public ?string $previewProof = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && self::allowedModulesFor($user) !== [];
    }

    private static function permissionModule(string $module): ?string
    {
        return match ($module) {
            'surat_masuk', 'surat_keluar' => 'surat',
            'legalisir' => 'legalisir',
            default => null,
        };
    }

    private static function allowedModulesFor(User $user): array
    {
        return array_filter(
            TuDataExchange::modules(),
            function (string $label, string $module) use ($user): bool {
                $permissionModule = self::permissionModule($module);

                return $permissionModule !== null && (
                    $user->canPerform($permissionModule, 'export') ||
                    $user->canPerform($permissionModule, 'import')
                );
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function allowedModules(): array
    {
        $user = Auth::user();

        return $user instanceof User ? self::allowedModulesFor($user) : [];
    }

    private function canImport(?string $module = null): bool
    {
        $user = Auth::user();
        $permissionModule = self::permissionModule((string) $module);

        return $user instanceof User && $permissionModule !== null
            && $user->canPerform($permissionModule, 'import');
    }

    private function canExport(?string $module = null): bool
    {
        $user = Auth::user();
        $permissionModule = self::permissionModule((string) $module);

        return $user instanceof User && $permissionModule !== null
            && $user->canPerform($permissionModule, 'export');
    }

    private function authorizeModule(string $module, string $action): void
    {
        $allowed = $action === 'import'
            ? $this->canImport($module)
            : $this->canExport($module);

        abort_unless($allowed, 403);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['module' => array_key_first($this->allowedModules()), 'format' => 'csv']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Modul dan pilihan berkas')
                ->description('Ekspor data sesuai filter, atau impor dari template yang disediakan. Impor tidak mengubah data yang sudah tersimpan.')
                ->schema([
                    Forms\Components\Select::make('module')->label('Modul administrasi')
                        ->options(fn (): array => $this->allowedModules())->required()->live(),
                    Forms\Components\Select::make('format')->label('Format ekspor / template')
                        ->options(['csv' => 'CSV (bisa dibuka Excel)', 'xlsx' => 'Excel (.xlsx, membutuhkan ekstensi zip PHP)'])->required(),
                ])->columns(2),
            Forms\Components\Section::make('Filter ekspor')
                ->description('Filter berlaku pada ekspor di halaman ini, bukan otomatis dari tabel Resource. Kosongkan untuk semua data.')
                ->schema([
                    Forms\Components\TextInput::make('search')->label('Cari nomor / nama / pengirim / klasifikasi / lokasi arsip')->maxLength(120),
                    Forms\Components\DatePicker::make('start')->label('Dari tanggal'),
                    Forms\Components\DatePicker::make('end')->label('Sampai tanggal'),
                ])->columns(3),
            Forms\Components\Section::make('Unggah untuk impor')
                ->description('Bagian sensitif: periksa pratinjau sebelum konfirmasi. Impor dicatat per akun dan dijalankan dalam satu transaksi.')
                ->schema([
                    Forms\Components\FileUpload::make('file')->label('Berkas CSV atau XLSX')->disk('local')
                        ->directory(fn (): string => 'tu-exchange/temp/'.Auth::id())->visibility('private')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(5120)->helperText('Download template terlebih dahulu agar susunan kolom tepat.'),
                ])->visible(fn (Forms\Get $get): bool => $this->canImport($get('module'))),
        ])->statePath('data');
    }

    public function downloadTemplate()
    {
        try {
            $state = $this->form->getState();
            $module = (string) $state['module'];
            abort_unless($this->canImport($module) || $this->canExport($module), 403);
            $rows = TuDataExchange::template($module);
            $file = 'template-tu-'.$module.'.'.$state['format'];

            return $state['format'] === 'xlsx' ? TuDataExchange::xlsxDownload($rows, $file) : TuDataExchange::csvDownload($rows, $file);
        } catch (Throwable $e) {
            Notification::make()->title('Gagal membuat template')->body($e->getMessage())->danger()->send();
        }
    }

    public function downloadExport()
    {
        try {
            $state = $this->form->getState();
            $module = (string) $state['module'];
            $this->authorizeModule($module, 'export');
            $rows = TuDataExchange::exportRows($module, $state['search'] ?? null, $state['start'] ?? null, $state['end'] ?? null);
            $filename = 'ekspor-tu-'.$module.'-'.date('Ymd-His').'.'.$state['format'];
            AuditLog::record('export', $module, null, null, ['file' => $filename, 'jumlah_baris' => max(0, count($rows) - 1)]);

            return $state['format'] === 'xlsx' ? TuDataExchange::xlsxDownload($rows, $filename) : TuDataExchange::csvDownload($rows, $filename);
        } catch (Throwable $e) {
            Notification::make()->title('Ekspor gagal')->body($e->getMessage())->danger()->send();
        }
    }

    private function secureUploadPath(string $path): string
    {
        $directory = 'tu-exchange/temp/'.Auth::id();
        if (! str_starts_with($path, $directory.'/') || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new RuntimeException('Lokasi berkas impor tidak sah.');
        }
        $full = Storage::disk('local')->path($path);
        $real = realpath($full);
        $root = realpath(Storage::disk('local')->path($directory));
        if (! $real || ! $root || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Berkas tidak berada di folder impor privat.');
        }

        return $real;
    }

    public function previewImport(): void
    {
        $this->reset(['preview', 'result', 'previewPath', 'previewModule', 'previewHash', 'previewProof']);
        try {
            $state = $this->form->getState();
            $module = (string) $state['module'];
            $this->authorizeModule($module, 'import');
            $path = $state['file'] ?? null;
            if (! is_string($path) || $path === '') {
                throw new RuntimeException('Pilih berkas terlebih dahulu.');
            }
            $file = $this->secureUploadPath($path);
            $analyzed = TuDataExchange::analyze($module, $file);
            unset($analyzed['rows']); // Data penuh TIDAK disimpan di state Livewire / browser.
            $this->preview = $analyzed;
            $this->previewPath = $path;
            $this->previewModule = $module;
            $this->previewHash = hash_file('sha256', $file);
            $this->previewProof = $this->proof();
            Notification::make()->title('Pratinjau selesai: belum ada data yang disimpan.')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Pratinjau gagal')->body($e->getMessage())->danger()->send();
        }
    }

    private function proof(): string
    {
        return hash_hmac('sha256', implode('|', [Auth::id(), $this->previewPath, $this->previewModule, $this->previewHash]), (string) config('app.key'));
    }

    public function commitImport(): void
    {
        try {
            if (! $this->preview || ! $this->previewPath || ! $this->previewHash || ! $this->previewProof) {
                throw new RuntimeException('Jalankan pratinjau sebelum impor.');
            }
            $this->authorizeModule((string) $this->previewModule, 'import');
            if (! hash_equals($this->proof(), $this->previewProof)) {
                throw new RuntimeException('Token pratinjau tidak cocok.');
            }
            $path = $this->secureUploadPath($this->previewPath);
            if (! hash_equals($this->previewHash, hash_file('sha256', $path))) {
                throw new RuntimeException('Berkas berubah setelah pratinjau. Unggah dan pratinjau ulang.');
            }
            if (($this->preview['invalid'] ?? 1) > 0) {
                throw new RuntimeException('Masih ada baris tidak valid. Perbaiki file lalu pratinjau ulang.');
            }
            $this->result = TuDataExchange::import((string) $this->previewModule, $path, (int) Auth::id());
            AuditLog::record('import', (string) $this->previewModule, null, null, $this->result);
            // Hapus hanya file unggahan milik sesi ini; tidak menghapus arsip/surat di storage lainnya.
            Storage::disk('local')->delete($this->previewPath);
            $this->reset(['preview', 'previewPath', 'previewModule', 'previewHash', 'previewProof']);
            $this->form->fill([
                'module' => $this->data['module'] ?? array_key_first($this->allowedModules()),
                'format' => $this->data['format'] ?? 'csv',
            ]);
            Notification::make()->title('Import berhasil: '.$this->result['success'].' baris baru tersimpan.')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Impor dibatalkan')->body($e->getMessage())->danger()->send();
        }
    }
}
