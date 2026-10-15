<?php

namespace App\Filament\Widgets;

use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $startOfMonth = now()->startOfMonth();

        // 1. Surat Masuk bulan ini
        $suratMasukBulanIni = SuratMasuk::where('tanggal_diterima', '>=', $startOfMonth)->count();

        // 2. Surat Keluar bulan ini
        $suratKeluarBulanIni = SuratKeluar::where('tanggal_surat', '>=', $startOfMonth)->count();

        // 3. Legalisir Menunggu
        $legalisirMenunggu = Legalisir::whereIn('status', ['diterima', 'diproses'])->count();

        // 4. Calon KP (TMT pangkat terakhir sudah >= 3.5 tahun atau status masih proses)
        $calonKp = Pegawai::whereNotNull('tmt_pangkat_terakhir')
            ->where('tmt_pangkat_terakhir', '<=', now()->subYears(4)->addMonths(6))
            ->where('is_active', true)
            ->count();

        // 5. KGB Jatuh Tempo (3 bulan ke depan)
        $kgbJatuhTempo = GajiBerkala::whereNotNull('tmt_berikutnya')
            ->where('tmt_berikutnya', '<=', now()->addMonths(3))
            ->where('tmt_berikutnya', '>=', now()->subMonths(1))
            ->where('status', '!=', 'diserahkan')
            ->count();

        return [
            Stat::make('Surat Masuk Bulan Ini', $suratMasukBulanIni)
                ->description('Total berkas surat dinas diterima')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('info'),

            Stat::make('Surat Keluar Bulan Ini', $suratKeluarBulanIni)
                ->description('Total surat resmi diterbitkan')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('success'),

            Stat::make('Legalisir Antrean', $legalisirMenunggu)
                ->description('Permohonan butuh diproses/verifikasi')
                ->descriptionIcon('heroicon-m-clock')
                ->color($legalisirMenunggu > 0 ? 'warning' : 'gray'),

            Stat::make('Calon Kenaikan Pangkat', $calonKp)
                ->description('Pegawai masuk estimasi 4 tahun')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($calonKp > 0 ? 'danger' : 'gray'),

            Stat::make('KGB Jatuh Tempo (3 Bln)', $kgbJatuhTempo)
                ->description('Gaji berkala perlu SK/proses')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($kgbJatuhTempo > 0 ? 'danger' : 'gray'),
        ];
    }
}
