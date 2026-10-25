<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'users',
        'pegawai',
        'kenaikan_pangkat',
        'gaji_berkala',
        'surat_masuk',
        'surat_keluar',
        'legalisir',
        'app_settings',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('lock_version')->default(0);
            });
        }

        Schema::table('audit_log', function (Blueprint $table): void {
            $table->string('user_username', 50)->nullable()->after('user_name');
            $table->string('user_role', 40)->nullable()->after('user_username');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->dropColumn(['user_username', 'user_role']);
        });

        foreach (array_reverse(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('lock_version');
            });
        }
    }
};
