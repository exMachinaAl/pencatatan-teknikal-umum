<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalisir', function (Blueprint $table): void {
            $table->unsignedSmallInteger('jumlah_dibawa')->nullable()->after('jumlah_lembar');
            $table->unsignedSmallInteger('jumlah_dikembalikan')->nullable()->after('jumlah_dibawa');
            $table->unsignedSmallInteger('jumlah_disimpan')->nullable()->after('jumlah_dikembalikan');
        });

        DB::table('legalisir')->orderBy('id')->chunkById(200, function ($records): void {
            foreach ($records as $record) {
                $dibawa = max(1, (int) ($record->jumlah_lembar ?? 1));
                $disimpan = 1;

                DB::table('legalisir')->where('id', $record->id)->update([
                    'jumlah_dibawa' => $dibawa,
                    'jumlah_dikembalikan' => max(0, $dibawa - $disimpan),
                    'jumlah_disimpan' => $disimpan,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('legalisir', function (Blueprint $table): void {
            $table->dropColumn(['jumlah_dibawa', 'jumlah_dikembalikan', 'jumlah_disimpan']);
        });
    }
};
