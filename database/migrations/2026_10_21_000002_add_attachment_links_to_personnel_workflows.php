<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->string('tautan_lampiran', 500)->nullable()->after('mk_bulan');
        });

        Schema::table('gaji_berkala', function (Blueprint $table): void {
            $table->string('tautan_lampiran', 500)->nullable()->after('mk_bulan');
        });
    }

    public function down(): void
    {
        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->dropColumn('tautan_lampiran');
        });

        Schema::table('gaji_berkala', function (Blueprint $table): void {
            $table->dropColumn('tautan_lampiran');
        });
    }
};
