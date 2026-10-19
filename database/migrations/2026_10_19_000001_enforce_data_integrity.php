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
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->string('sequence_key', 100)->primary();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });

        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->string('integrity_key', 64)->nullable()->after('pegawai_id');
        });

        Schema::table('gaji_berkala', function (Blueprint $table): void {
            $table->string('integrity_key', 64)->nullable()->after('pegawai_id');
        });

        $this->backfillIntegrityKeys(
            'kenaikan_pangkat',
            fn (object $row): string => hash('sha256', implode('|', [
                'kp',
                $row->pegawai_id,
                $row->jenis,
                Carbon::parse($row->tmt)->toDateString(),
            ])),
            ['id', 'pegawai_id', 'jenis', 'tmt']
        );

        $this->backfillIntegrityKeys(
            'gaji_berkala',
            fn (object $row): string => hash('sha256', implode('|', [
                'kgb',
                $row->pegawai_id,
                Carbon::parse($row->tmt)->toDateString(),
            ])),
            ['id', 'pegawai_id', 'tmt']
        );

        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->unique('integrity_key', 'kenaikan_pangkat_integrity_key_unique');
        });

        Schema::table('gaji_berkala', function (Blueprint $table): void {
            $table->unique('integrity_key', 'gaji_berkala_integrity_key_unique');
        });
    }

    private function backfillIntegrityKeys(string $table, callable $makeKey, array $columns): void
    {
        $seen = [];

        DB::table($table)
            ->whereNotNull('tmt')
            ->orderBy('id')
            ->get($columns)
            ->each(function (object $row) use ($table, $makeKey, &$seen): void {
                $key = $makeKey($row);

                // Data duplikat lama dipertahankan. Satu record kanonik diberi key
                // agar duplikat baru tetap ditolak tanpa menghapus data produksi.
                if (isset($seen[$key])) {
                    return;
                }

                $seen[$key] = true;
                DB::table($table)->where('id', $row->id)->update(['integrity_key' => $key]);
            });
    }

    public function down(): void
    {
        Schema::table('kenaikan_pangkat', function (Blueprint $table): void {
            $table->dropUnique('kenaikan_pangkat_integrity_key_unique');
            $table->dropColumn('integrity_key');
        });

        Schema::table('gaji_berkala', function (Blueprint $table): void {
            $table->dropUnique('gaji_berkala_integrity_key_unique');
            $table->dropColumn('integrity_key');
        });

        Schema::dropIfExists('document_sequences');
    }
};
