<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalisir', function (Blueprint $table): void {
            $table->string('tautan_lampiran', 500)->nullable()->after('file_dokumen_path');
        });
    }

    public function down(): void
    {
        Schema::table('legalisir', function (Blueprint $table): void {
            $table->dropColumn('tautan_lampiran');
        });
    }
};
