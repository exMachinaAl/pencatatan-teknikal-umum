<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_keluar', function (Blueprint $table): void {
            // Dipertahankan hanya untuk kompatibilitas data lama. Surat baru memakai
            // nomor resmi sekolah dan tidak membuat nomor urut internal.
            $table->integer('nomor_urut')->nullable()->change();
        });

        DB::table('app_settings')->where('key', 'format_surat_keluar')->delete();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $maximumByYear = DB::table('surat_keluar')
                ->whereNotNull('nomor_urut')
                ->selectRaw('tahun, MAX(nomor_urut) as maksimum')
                ->groupBy('tahun')
                ->pluck('maksimum', 'tahun')
                ->map(fn ($value): int => (int) $value)
                ->all();

            DB::table('surat_keluar')
                ->whereNull('nomor_urut')
                ->orderBy('tahun')
                ->orderBy('id')
                ->get(['id', 'tahun'])
                ->each(function (object $surat) use (&$maximumByYear): void {
                    $tahun = (int) $surat->tahun;
                    $maximumByYear[$tahun] = ($maximumByYear[$tahun] ?? 0) + 1;
                    DB::table('surat_keluar')->where('id', $surat->id)->update([
                        'nomor_urut' => $maximumByYear[$tahun],
                    ]);
                });
        });

        Schema::table('surat_keluar', function (Blueprint $table): void {
            $table->integer('nomor_urut')->nullable(false)->change();
        });

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'format_surat_keluar'],
            [
                'value' => '421/{nomor_urut}/SMAN1/{tahun}',
                'deskripsi' => 'Format kode nomor surat keluar ({nomor_urut}, {tahun}, {kode_klasifikasi})',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
