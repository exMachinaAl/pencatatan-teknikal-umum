<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalisir', function (Blueprint $table): void {
            $table->string('lokasi_arsip_fisik', 150)->nullable()->after('nomor_dokumen');
            $table->index('lokasi_arsip_fisik', 'legalisir_lokasi_arsip_fisik_index');
        });
    }

    public function down(): void
    {
        Schema::table('legalisir', function (Blueprint $table): void {
            $table->dropIndex('legalisir_lokasi_arsip_fisik_index');
            $table->dropColumn('lokasi_arsip_fisik');
        });
    }
};
