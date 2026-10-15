<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Backup database MySQL ke format SQL di folder privat terproteksi';

    public function handle(): int
    {
        $this->info('Memulai pencadangan database...');

        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);

        $backupDir = storage_path('app/backups');
        if (! file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = "backup-{$database}-" . date('Y-m-d_H-i-s') . ".sql";
        $filepath = "{$backupDir}/{$filename}";

        // Coba mysqldump jika ada di PATH / Laragon
        $laragonMysqlDump = 'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe';
        $mysqldump = file_exists($laragonMysqlDump) ? "\"{$laragonMysqlDump}\"" : 'mysqldump';

        $passwordParam = $password ? "-p\"{$password}\"" : '';
        $cmd = "{$mysqldump} -h {$host} -P {$port} -u {$username} {$passwordParam} {$database} > \"{$filepath}\"";

        exec($cmd, $output, $returnVar);

        // Jika mysqldump tidak berhasil, gunakan fallback export via PHP PDO
        if ($returnVar !== 0 || ! file_exists($filepath) || filesize($filepath) === 0) {
            $this->info('Menggunakan fallback export via PDO PHP...');
            $tables = DB::select('SHOW TABLES');
            $dbKey = "Tables_in_{$database}";
            $sqlDump = "-- SISTEM INFORMASI TU SEKOLAH NEGERI\n-- BACKUP TANGGAL: " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $t) {
                $table = $t->$dbKey;
                $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
                $sqlDump .= "\n\n" . $createTable[0]->{'Create Table'} . ";\n\n";

                $rows = DB::table($table)->get();
                foreach ($rows as $row) {
                    $values = array_map(function ($val) {
                        return is_null($val) ? 'NULL' : DB::getPdo()->quote($val);
                    }, (array) $row);
                    $sqlDump .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                }
            }

            file_put_contents($filepath, $sqlDump);
        }

        AuditLog::record('export', 'database_backup', null, null, ['file' => $filename, 'size' => filesize($filepath)]);

        $this->info("Backup berhasil dibuat: {$filepath} (" . round(filesize($filepath) / 1024, 2) . ' KB)');

        return Command::SUCCESS;
    }
}
