<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-database {--retain=30 : Jumlah backup terbaru yang dipertahankan}';

    protected $description = 'Membuat bundle privat database MySQL dan lampiran lokal';

    public function handle(): int
    {
        $lock = Cache::lock('tu-database-backup', 1800);
        if (! $lock->get()) {
            $this->error('Proses backup lain masih berjalan.');

            return Command::FAILURE;
        }

        $sqlPath = null;
        $bundlePath = null;

        try {
            if (DB::getDriverName() !== 'mysql') {
                throw new RuntimeException('Backup operasional hanya mendukung koneksi MySQL.');
            }
            if (! class_exists(ZipArchive::class)) {
                throw new RuntimeException('Ekstensi PHP zip wajib aktif untuk membuat bundle backup.');
            }

            $database = (string) config('database.connections.mysql.database');
            $backupDir = storage_path('secure-backups');
            if (! is_dir($backupDir) && ! mkdir($backupDir, 0700, true) && ! is_dir($backupDir)) {
                throw new RuntimeException('Folder backup privat tidak dapat dibuat.');
            }

            $stamp = now()->format('Y-m-d_H-i-s-u').'-'.Str::lower(Str::random(6));
            $safeDatabase = preg_replace('/[^A-Za-z0-9._-]/', '_', $database) ?: 'database';
            $filename = "backup-{$safeDatabase}-{$stamp}.zip";
            $bundlePath = $backupDir.DIRECTORY_SEPARATOR.$filename;
            $sqlPath = $backupDir.DIRECTORY_SEPARATOR.'.'.$stamp.'.part.sql';

            $this->dumpDatabase($sqlPath, $database);
            $this->createBundle($bundlePath, $sqlPath);
            $this->removeOldBackups($backupDir, max(1, (int) $this->option('retain')));

            AuditLog::record('backup_buat', 'database_backup', null, null, [
                'file' => $filename,
                'size' => filesize($bundlePath),
                'includes_local_files' => true,
            ]);

            $this->info("Backup berhasil dibuat: {$filename} (".round(filesize($bundlePath) / 1024, 2).' KB)');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($bundlePath && is_file($bundlePath)) {
                unlink($bundlePath);
            }
            $this->error('Backup gagal: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            if ($sqlPath && is_file($sqlPath)) {
                unlink($sqlPath);
            }
            $lock->release();
        }
    }

    private function dumpDatabase(string $target, string $database): void
    {
        $connection = config('database.connections.mysql');
        $laragon = 'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe';
        $binary = is_file($laragon) ? $laragon : 'mysqldump';
        $handle = fopen($target, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Berkas SQL sementara tidak dapat dibuat.');
        }

        $process = new Process([
            $binary,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--host='.(string) $connection['host'],
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.(string) $connection['username'],
            '--default-character-set=utf8mb4',
            $database,
        ], null, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);
        $process->setTimeout(900);
        try {
            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            if (is_file($target)) {
                unlink($target);
            }
            $this->warn('mysqldump tidak tersedia atau gagal; memakai fallback snapshot PDO.');
            $this->dumpWithPdo($target, $database);
        }
    }

    private function dumpWithPdo(string $target, string $database): void
    {
        $handle = fopen($target, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Berkas fallback SQL tidak dapat dibuat.');
        }

        try {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            DB::transaction(function () use ($handle, $database): void {
                fwrite($handle, '-- BACKUP SISTEM INFORMASI TU'.PHP_EOL.'-- '.now()->toDateTimeString().PHP_EOL.PHP_EOL);
                fwrite($handle, 'SET NAMES utf8mb4;'.PHP_EOL.'SET FOREIGN_KEY_CHECKS=0;'.PHP_EOL);
                $dbKey = 'Tables_in_'.$database;
                foreach (DB::select('SHOW TABLES') as $tableRow) {
                    $table = (string) $tableRow->{$dbKey};
                    $escapedTable = str_replace('`', '``', $table);
                    $create = DB::select("SHOW CREATE TABLE `{$escapedTable}`");
                    fwrite($handle, PHP_EOL.$create[0]->{'Create Table'}.';'.PHP_EOL);

                    DB::table($table)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use ($handle, $escapedTable): void {
                        foreach ($rows as $row) {
                            $values = array_map(
                                fn ($value) => $value === null ? 'NULL' : DB::getPdo()->quote((string) $value),
                                (array) $row,
                            );
                            fwrite($handle, "INSERT INTO `{$escapedTable}` VALUES (".implode(', ', $values).');'.PHP_EOL);
                        }
                    });
                }
                fwrite($handle, PHP_EOL.'SET FOREIGN_KEY_CHECKS=1;'.PHP_EOL);
            }, 3);
        } finally {
            fclose($handle);
        }
    }

    private function createBundle(string $bundlePath, string $sqlPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($bundlePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Bundle backup tidak dapat dibuat.');
        }

        try {
            if (! $zip->addFile($sqlPath, 'database.sql')) {
                throw new RuntimeException('Database SQL gagal dimasukkan ke bundle backup.');
            }
            $root = realpath(storage_path('app'));
            $directories = [
                'arsip_surat_masuk', 'arsip_surat_keluar', 'bukti_pengiriman',
                'legalisir_dokumen', 'legalisir_serah_terima',
                'profil_pegawai', 'profil_pengguna',
            ];

            foreach ($directories as $directory) {
                $path = realpath(storage_path('app/'.$directory));
                if (! $root || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                );
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                        if (! $zip->addFile($file->getPathname(), 'files/'.$relative)) {
                            throw new RuntimeException("Lampiran {$relative} gagal dimasukkan ke bundle backup.");
                        }
                    }
                }
            }

            if (! $zip->addFromString('README-PEMULIHAN.txt', "Bundle berisi database.sql dan direktori files/.\nPulihkan hanya pada server terisolasi setelah verifikasi dan otorisasi administrator.\n")) {
                throw new RuntimeException('Petunjuk pemulihan gagal dimasukkan ke bundle backup.');
            }

            $password = (string) config('security.backup_archive_password');
            if ($password !== '') {
                if (mb_strlen($password) < 16) {
                    throw new RuntimeException('BACKUP_ARCHIVE_PASSWORD minimal 16 karakter.');
                }
                $zip->setPassword($password);
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = $zip->getNameIndex($index);
                    if ($name !== false && ! $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password)) {
                        throw new RuntimeException('Enkripsi AES backup tidak didukung oleh ekstensi zip ini.');
                    }
                }
            } elseif (app()->environment('production')) {
                throw new RuntimeException('BACKUP_ARCHIVE_PASSWORD wajib diisi pada production.');
            }
        } catch (\Throwable $e) {
            $zip->close();

            throw $e;
        }

        if (! $zip->close()) {
            throw new RuntimeException('Bundle backup gagal diselesaikan.');
        }
    }

    private function removeOldBackups(string $directory, int $retain): void
    {
        $files = glob($directory.DIRECTORY_SEPARATOR.'backup-*.zip') ?: [];
        usort($files, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));
        foreach (array_slice($files, $retain) as $file) {
            unlink($file);
        }
    }
}
