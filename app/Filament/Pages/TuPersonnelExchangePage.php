<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Services\TuDataExchange;
use App\Services\TuPersonnelExchange;
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

/** Pertukaran data kepegawaian berbasis template versi struktur input terbaru. */
class TuPersonnelExchangePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?string $navigationGroup = 'Administrasi Kepegawaian';

    protected static ?string $navigationLabel = 'Export & Import Kepegawaian';

    protected static ?string $title = 'Export & Import Pegawai, KP dan KGB';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.tu-personnel-exchange';

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

        return $user && $user->is_active && count(TuPersonnelExchange::modules($user->role)) > 0;
    }

    private function canImport(?string $module): bool
    {
        $user = Auth::user();

        return $user && $user->is_active && $module && TuPersonnelExchange::authorized($user->role, $module, true);
    }

    private function ensurePermission(string $module, bool $write): void
    {
        $user = Auth::user();
        abort_unless($user && $user->is_active && TuPersonnelExchange::authorized($user->role, $module, $write), 403);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['module' => 'pegawai', 'format' => 'csv']);
    }

    public function form(Form $form): Form
    {
        $role = Auth::user()?->role ?? '';

        return $form->schema([
            Forms\Components\Section::make('Data kepegawaian')
                ->description('Gunakan template terbaru. Impor hanya menambah data; KP/KGB memerlukan pegawai yang sudah terdaftar.')
                ->schema([
                    Forms\Components\Select::make('module')->label('Modul')->options(TuPersonnelExchange::modules($role))
                        ->required()->live()->afterStateUpdated(fn () => $this->reset(['preview', 'result', 'previewPath', 'previewModule', 'previewHash', 'previewProof'])),
                    Forms\Components\Select::make('format')->label('Format')->options([
                        'csv' => 'CSV UTF-8', 'xlsx' => 'Excel XLSX (PHP zip + dom)',
                    ])->required(),
                ])->columns(2),
            Forms\Components\Section::make('Filter ekspor')
                ->description('Rentang tanggal menggunakan tanggal pencatatan data di aplikasi dan tidak mengikuti filter tabel modul.')
                ->schema([
                    Forms\Components\TextInput::make('search')->label('Cari nama atau NIP')->maxLength(120),
                    Forms\Components\DatePicker::make('start')->label('Tanggal dari'),
                    Forms\Components\DatePicker::make('end')->label('Tanggal sampai'),
                ])->columns(3),
            Forms\Components\Section::make('Unggah file impor')
                ->description('Bagian sensitif: periksa pratinjau sebelum konfirmasi. Impor dicatat per akun dan dijalankan dalam satu transaksi.')
                ->schema([
                    Forms\Components\FileUpload::make('file')->label('CSV / XLSX')->disk('local')
                        ->directory(fn () => 'tu-exchange/personnel-temp/'.Auth::id())->visibility('private')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(5120),
                ])->visible(fn (Forms\Get $get) => $this->canImport($get('module'))),
        ])->statePath('data');
    }

    public function downloadTemplate()
    {
        try {
            $state = $this->form->getState();
            $module = (string) $state['module'];
            $this->ensurePermission($module, false);
            $rows = TuPersonnelExchange::template($module);
            $name = 'template-kepegawaian-'.$module.'.'.$state['format'];

            return $state['format'] === 'xlsx' ? TuDataExchange::xlsxDownload($rows, $name) : TuDataExchange::csvDownload($rows, $name);
        } catch (Throwable $e) {
            Notification::make()->title('Template gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function downloadExport()
    {
        try {
            $state = $this->form->getState();
            $module = (string) $state['module'];
            $this->ensurePermission($module, false);
            $rows = TuPersonnelExchange::export($module, $state['search'] ?? null, $state['start'] ?? null, $state['end'] ?? null);
            $name = 'ekspor-kepegawaian-'.$module.'-'.date('Ymd-His').'.'.$state['format'];
            AuditLog::record('export', $module, null, null, ['file' => $name, 'jumlah_baris' => max(0, count($rows) - 1)]);

            return $state['format'] === 'xlsx' ? TuDataExchange::xlsxDownload($rows, $name) : TuDataExchange::csvDownload($rows, $name);
        } catch (Throwable $e) {
            Notification::make()->title('Ekspor gagal')->body($e->getMessage())->danger()->send();
        }
    }

    private function uploadPath(string $path): string
    {
        if (! str_starts_with($path, 'tu-exchange/personnel-temp/'.Auth::id().'/') || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new RuntimeException('Path unggahan tidak diperbolehkan.');
        }
        $full = Storage::disk('local')->path($path);
        $real = realpath($full);
        $root = realpath(Storage::disk('local')->path('tu-exchange/personnel-temp/'.Auth::id()));
        if (! $real || ! $root || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Berkas tidak ditemukan di penyimpanan privat.');
        }

        return $real;
    }

    private function proof(): string
    {
        return hash_hmac('sha256', implode('|', [Auth::id(), $this->previewModule, $this->previewPath, $this->previewHash]), (string) config('app.key'));
    }

    public function previewImport(): void
    {
        $this->reset(['preview', 'result', 'previewPath', 'previewModule', 'previewHash', 'previewProof']);
        try {
            $state = $this->form->getState();
            $module = (string) $state['module'];
            $this->ensurePermission($module, true);
            $relative = $state['file'] ?? null;
            if (! is_string($relative) || $relative === '') {
                throw new RuntimeException('Pilih CSV/XLSX terlebih dahulu.');
            }
            $file = $this->uploadPath($relative);
            $check = TuPersonnelExchange::analyze($module, $file);
            unset($check['rows']);
            $this->preview = $check;
            $this->previewModule = $module;
            $this->previewPath = $relative;
            $this->previewHash = hash_file('sha256', $file);
            $this->previewProof = $this->proof();
            Notification::make()->title('Pratinjau berhasil. Belum ada data tersimpan.')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Pratinjau gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function commitImport(): void
    {
        try {
            if (! $this->previewModule || ! $this->previewPath || ! $this->previewHash || ! $this->previewProof || ! $this->preview) {
                throw new RuntimeException('Pratinjau diperlukan sebelum impor.');
            }
            $this->ensurePermission($this->previewModule, true);
            if (! hash_equals($this->proof(), $this->previewProof)) {
                throw new RuntimeException('Konfirmasi pratinjau tidak cocok.');
            }
            if (($this->preview['invalid'] ?? 1) > 0 || ($this->preview['valid'] ?? 0) < 1) {
                throw new RuntimeException('Tidak ada data valid siap disimpan.');
            }
            $file = $this->uploadPath($this->previewPath);
            if (! hash_equals($this->previewHash, hash_file('sha256', $file))) {
                throw new RuntimeException('Isi file berubah; lakukan pratinjau ulang.');
            }
            $this->result = TuPersonnelExchange::import($this->previewModule, $file, (int) Auth::id());
            AuditLog::record('import', $this->previewModule, null, null, $this->result);
            Storage::disk('local')->delete($this->previewPath);
            $this->reset(['preview', 'previewPath', 'previewModule', 'previewHash', 'previewProof']);
            Notification::make()->title('Berhasil menambah '.$this->result['success'].' data.')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Impor dibatalkan')->body($e->getMessage())->danger()->send();
        }
    }
}
