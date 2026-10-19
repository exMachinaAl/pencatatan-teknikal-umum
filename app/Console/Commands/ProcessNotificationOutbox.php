<?php

namespace App\Console\Commands;

use App\Models\NotificationOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessNotificationOutbox extends Command
{
    protected $signature = 'notifications:process {--limit=25 : Maksimum notifikasi yang diproses}';

    protected $description = 'Kirim email legalisir yang mengantre dan tandai WhatsApp yang menunggu provider';

    public function handle(): int
    {
        $items = NotificationOutbox::query()
            ->where('status', 'antre')
            ->oldest()
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($items as $item) {
            if (! NotificationOutbox::destinationIsValid((string) $item->kanal, (string) $item->tujuan)) {
                $item->update([
                    'status' => 'gagal',
                    'percobaan' => $item->percobaan + 1,
                    'error_terakhir' => 'Tujuan notifikasi tidak valid.',
                ]);
                $this->error("Notifikasi #{$item->id} ditolak karena tujuan tidak valid.");

                continue;
            }

            if ($item->kanal === 'whatsapp') {
                $item->update([
                    'status' => 'menunggu_provider',
                    'error_terakhir' => 'Provider WhatsApp belum dikonfigurasi.',
                ]);
                $this->warn("WhatsApp #{$item->id} menunggu provider.");

                continue;
            }

            try {
                $payload = $item->payload ?? [];
                Mail::raw(
                    "Legalisir {$payload['nomor_antrean']} sudah siap diambil. Pantau status: {$payload['tracking_url']}",
                    fn ($mail) => $mail->to($item->tujuan)->subject('Legalisir siap diambil')
                );

                $item->update([
                    'status' => 'terkirim',
                    'percobaan' => $item->percobaan + 1,
                    'error_terakhir' => null,
                    'dikirim_pada' => now(),
                ]);
                $this->info("Email #{$item->id} terkirim.");
            } catch (Throwable $exception) {
                report($exception);
                $item->update([
                    'status' => 'gagal',
                    'percobaan' => $item->percobaan + 1,
                    'error_terakhir' => 'Pengiriman email gagal. Periksa log aplikasi.',
                ]);
                $this->error("Notifikasi #{$item->id} gagal.");
            }
        }

        $this->line("Selesai memproses {$items->count()} notifikasi.");

        return self::SUCCESS;
    }
}
