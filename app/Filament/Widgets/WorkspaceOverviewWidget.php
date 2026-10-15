<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BackupManager;
use App\Filament\Pages\GoogleFormImport;
use App\Filament\Resources\AppSettingResource;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\GajiBerkalaResource;
use App\Filament\Resources\KenaikanPangkatResource;
use App\Filament\Resources\LegalisirResource;
use App\Filament\Resources\PegawaiResource;
use App\Filament\Resources\SuratKeluarResource;
use App\Filament\Resources\SuratMasukResource;
use App\Filament\Resources\UserResource;
use App\Models\GajiBerkala;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Filament\Widgets\Widget;

class WorkspaceOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.workspace-overview-widget';

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $suratMasuk = SuratMasuk::query()
            ->whereIn('status', ['baru', 'didisposisi'])
            ->orderByRaw("CASE WHEN sifat = 'segera' THEN 0 WHEN sifat = 'penting' THEN 1 ELSE 2 END")
            ->latest('tanggal_diterima')
            ->latest('id')
            ->first();

        $suratKeluar = SuratKeluar::query()
            ->where('status', 'draf')
            ->orderByRaw("CASE WHEN sifat = 'segera' THEN 0 WHEN sifat = 'penting' THEN 1 ELSE 2 END")
            ->latest('tanggal_surat')
            ->latest('id')
            ->first();

        $modules = [
            [
                'label' => 'Surat Masuk',
                'description' => 'Agenda dan disposisi',
                'icon' => 'heroicon-o-inbox-arrow-down',
                'url' => SuratMasukResource::getUrl(),
            ],
            [
                'label' => 'Surat Keluar',
                'description' => 'Konsep dan arsip',
                'icon' => 'heroicon-o-paper-airplane',
                'url' => SuratKeluarResource::getUrl(),
            ],
            [
                'label' => 'Pegawai',
                'description' => 'Data kepegawaian',
                'icon' => 'heroicon-o-users',
                'url' => PegawaiResource::getUrl(),
            ],
            [
                'label' => 'Kenaikan Pangkat',
                'description' => 'Usulan dan status',
                'icon' => 'heroicon-o-arrow-trending-up',
                'url' => KenaikanPangkatResource::getUrl(),
            ],
            [
                'label' => 'Gaji Berkala',
                'description' => 'Jadwal KGB',
                'icon' => 'heroicon-o-banknotes',
                'url' => GajiBerkalaResource::getUrl(),
            ],
            [
                'label' => 'Legalisir',
                'description' => 'Permohonan layanan',
                'icon' => 'heroicon-o-document-check',
                'url' => LegalisirResource::getUrl(),
            ],
        ];

        $utilities = [];

        foreach ([UserResource::class, AppSettingResource::class, AuditLogResource::class] as $resource) {
            if ($resource::canViewAny()) {
                $utilities[] = ['label' => $resource::getNavigationLabel(), 'url' => $resource::getUrl()];
            }
        }

        foreach ([GoogleFormImport::class, BackupManager::class] as $page) {
            if ($page::canAccess()) {
                $utilities[] = ['label' => $page::getNavigationLabel(), 'url' => $page::getUrl()];
            }
        }

        $attention = [
            [
                'label' => 'Legalisir menunggu proses',
                'count' => Legalisir::perluTindakLanjut()->count(),
                'detail' => 'permohonan perlu diverifikasi',
                'url' => LegalisirResource::getUrl('index', [
                    'tableFilters' => ['perlu_tindak_lanjut' => ['isActive' => true]],
                ]),
                'tone' => 'amber',
            ],
            [
                'label' => 'KGB mendekati jatuh tempo',
                'count' => GajiBerkala::perluTindakLanjut()->count(),
                'detail' => 'belum diserahkan; satu bulan lalu sampai tiga bulan ke depan',
                'url' => GajiBerkalaResource::getUrl('index', [
                    'tableFilters' => ['perlu_tindak_lanjut' => ['isActive' => true]],
                ]),
                'tone' => 'rose',
            ],
            [
                'label' => 'Calon kenaikan pangkat',
                'count' => Pegawai::active()->calonKp()->count(),
                'detail' => 'pegawai masuk periode persiapan',
                'url' => PegawaiResource::getUrl('index', [
                    'tableFilters' => [
                        'calon_kp' => ['isActive' => true],
                        'is_active' => ['value' => true],
                    ],
                ]),
                'tone' => 'emerald',
            ],
        ];

        return compact('suratMasuk', 'suratKeluar', 'modules', 'utilities', 'attention');
    }
}
