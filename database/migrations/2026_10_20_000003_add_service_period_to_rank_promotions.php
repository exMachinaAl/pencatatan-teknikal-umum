<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->unsignedSmallInteger('mk_tahun')->default(0)->after('tmt');
            $table->unsignedTinyInteger('mk_bulan')->default(0)->after('mk_tahun');
        });
    }

    public function down(): void
    {
        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->dropColumn(['mk_tahun', 'mk_bulan']);
        });
    }
};
