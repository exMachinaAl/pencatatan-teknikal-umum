<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conflictCount = DB::table('kenaikan_pangkat')
            ->whereNotNull('tmt')
            ->select(['pegawai_id', 'tmt'])
            ->groupBy('pegawai_id', 'tmt')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($conflictCount > 0) {
            throw new RuntimeException(
                "Migration dibatalkan: ditemukan {$conflictCount} konflik kenaikan pangkat dengan pegawai dan TMT yang sama. Periksa, gabungkan, atau koreksi TMT data tersebut sebelum menjalankan migration kembali."
            );
        }

        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->string('decision_key', 64)->nullable()->after('integrity_key');
        });

        DB::table('kenaikan_pangkat')
            ->whereNotNull('tmt')
            ->orderBy('id')
            ->get(['id', 'pegawai_id', 'tmt'])
            ->each(function (object $row): void {
                $date = Carbon::parse($row->tmt)->toDateString();
                $key = hash('sha256', implode('|', ['kp-decision', $row->pegawai_id, $date]));

                DB::table('kenaikan_pangkat')
                    ->where('id', $row->id)
                    ->update(['decision_key' => $key]);
            });

        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->unique('decision_key', 'kenaikan_pangkat_decision_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->dropUnique('kenaikan_pangkat_decision_key_unique');
            $table->dropColumn('decision_key');
        });
    }
};
