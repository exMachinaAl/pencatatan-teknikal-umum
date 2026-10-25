<?php

namespace App\Console\Commands;

use App\Models\KenaikanPangkat;
use Illuminate\Console\Command;

class SyncEffectivePromotions extends Command
{
    protected $signature = 'app:sync-effective-promotions';

    protected $description = 'Menerapkan golongan pegawai untuk SK kenaikan pangkat yang TMT-nya sudah berlaku';

    public function handle(): int
    {
        $applied = 0;

        KenaikanPangkat::query()
            ->whereIn('status', ['sk_terbit', 'diserahkan'])
            ->whereNotNull('golongan_baru_id')
            ->whereNotNull('tmt')
            ->whereDate('tmt', '<=', now()->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($promotions) use (&$applied): void {
                foreach ($promotions as $promotion) {
                    if ($promotion->applyEffectiveRank()) {
                        $applied++;
                    }
                }
            });

        $this->info("Sinkronisasi selesai: {$applied} golongan pegawai diperbarui.");

        return self::SUCCESS;
    }
}
