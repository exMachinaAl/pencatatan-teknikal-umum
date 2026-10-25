<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupManager extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $navigationLabel = 'Pencadangan Database';

    protected static ?string $title = 'Pusat Pencadangan & Pemulihan Database (Backup & Restore)';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.backup-manager';

    public static function canAccess(): bool
    {
        return auth()->user()?->canPerform('backup', 'view') ?? false;
    }

    public function createBackup(): void
    {
        abort_unless(auth()->user()?->canPerform('backup', 'manage'), 403);

        try {
            $exitCode = Artisan::call('app:backup-database');
            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'Perintah backup gagal.');
            }
            Notification::make()
                ->title('Pencadangan database berhasil dibuat.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal membuat pencadangan: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function downloadBackup(string $filename): ?BinaryFileResponse
    {
        abort_unless(auth()->user()?->canPerform('backup', 'manage'), 403);

        $filePath = $this->resolveBackupPath($filename);
        AuditLog::record('backup_unduh', 'database_backup', null, null, [
            'file' => $filename,
            'size' => filesize($filePath),
        ]);

        return response()->download($filePath);
    }

    public function deleteBackup(string $filename): void
    {
        abort_unless(auth()->user()?->canPerform('backup', 'manage'), 403);

        $filePath = $this->resolveBackupPath($filename);
        AuditLog::record('backup_hapus', 'database_backup', null, ['file' => $filename, 'size' => filesize($filePath)], null);
        unlink($filePath);
        Notification::make()->title('Berkas backup telah dihapus.')->success()->send();
    }

    private function resolveBackupPath(string $filename): string
    {
        if (
            $filename !== basename($filename) ||
            str_contains($filename, '/') ||
            str_contains($filename, '\\') ||
            ! preg_match('/\Abackup-[A-Za-z0-9._-]+\.zip\z/D', $filename)
        ) {
            abort(404);
        }

        $backupRoot = realpath(storage_path('secure-backups'));
        $filePath = $backupRoot ? realpath($backupRoot.DIRECTORY_SEPARATOR.$filename) : false;

        if (! $backupRoot || ! $filePath || ! is_file($filePath) || dirname($filePath) !== $backupRoot) {
            abort(404);
        }

        return $filePath;
    }

    public function getBackupFilesProperty(): array
    {
        $backupDir = storage_path('secure-backups');
        if (! file_exists($backupDir)) {
            return [];
        }

        $files = glob("{$backupDir}/*.zip");
        $results = [];

        foreach ($files as $file) {
            $results[] = [
                'name' => basename($file),
                'size' => round(filesize($file) / 1024, 2).' KB',
                'created_at' => date('d M Y H:i:s', filemtime($file)),
            ];
        }

        usort($results, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $results;
    }
}
