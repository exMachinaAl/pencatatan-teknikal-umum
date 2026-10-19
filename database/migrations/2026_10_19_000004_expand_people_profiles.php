<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table): void {
            $table->string('email', 160)->nullable()->unique()->after('nama');
            $table->string('nomor_telepon', 30)->nullable()->after('email');
            $table->string('tempat_lahir', 100)->nullable()->after('nomor_telepon');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('jenis_kelamin', 1)->nullable()->after('tanggal_lahir');
            $table->text('alamat')->nullable()->after('jenis_kelamin');
            $table->string('unit_kerja', 120)->nullable()->after('jabatan');
            $table->string('pendidikan_terakhir', 100)->nullable()->after('unit_kerja');
            $table->date('tanggal_mulai_tugas')->nullable()->after('pendidikan_terakhir');
            $table->string('foto_path', 500)->nullable()->after('tanggal_mulai_tugas');

            $table->index('nomor_telepon');
            $table->index('unit_kerja');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('pegawai_id')->nullable()->unique()->after('id')
                ->constrained('pegawai')->nullOnDelete();
            $table->string('nomor_telepon', 30)->nullable()->after('email');
            $table->string('jabatan', 120)->nullable()->after('nomor_telepon');
            $table->string('unit_kerja', 120)->nullable()->after('jabatan');
            $table->string('foto_path', 500)->nullable()->after('unit_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['pegawai_id']);
            $table->dropUnique(['pegawai_id']);
            $table->dropColumn(['pegawai_id', 'nomor_telepon', 'jabatan', 'unit_kerja', 'foto_path']);
        });

        Schema::table('pegawai', function (Blueprint $table): void {
            $table->dropIndex(['nomor_telepon']);
            $table->dropIndex(['unit_kerja']);
            $table->dropUnique(['email']);
            $table->dropColumn([
                'email', 'nomor_telepon', 'tempat_lahir', 'tanggal_lahir',
                'jenis_kelamin', 'alamat', 'unit_kerja', 'pendidikan_terakhir',
                'tanggal_mulai_tugas', 'foto_path',
            ]);
        });
    }
};
