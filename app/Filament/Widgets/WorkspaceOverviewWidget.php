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
use App\Models\Disposisi;
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

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $user = auth()->user();
        $canViewSurat = $user?->canPerform('surat', 'view') ?? false;
        $canViewLegalisir = $user?->canPerform('legalisir', 'view') ?? false;
        $canViewKepegawaian = $user?->canPerform('kepegawaian', 'view') ?? false;

        $suratMasuk = $canViewSurat ? SuratMasuk::query()
            ->whereIn('status', ['baru', 'didisposisi'])
            ->orderByRaw("CASE WHEN sifat = 'segera' THEN 0 WHEN sifat = 'penting' THEN 1 ELSE 2 END")
            ->latest('tanggal_diterima')
            ->latest('id')
            ->first() : null;

        $suratKeluar = $canViewSurat ? SuratKeluar::query()
            ->where('status', 'draf')
            ->orderByRaw("CASE WHEN sifat = 'segera' THEN 0 WHEN sifat = 'penting' THEN 1 ELSE 2 END")
            ->latest('tanggal_surat')
            ->latest('id')
            ->first() : null;

        $moduleDefinitions = [
            [
                'label' => 'Surat Masuk',
                'description' => 'Agenda dan disposisi',
                'icon' => 'heroicon-o-inbox-arrow-down',
                'resource' => SuratMasukResource::class,
            ],
            [
                'label' => 'Surat Keluar',
                'description' => 'Konsep dan arsip',
                'icon' => 'heroicon-o-paper-airplane',
                'resource' => SuratKeluarResource::class,
            ],
            [
                'label' => 'Pegawai',
                'description' => 'Data kepegawaian',
                'icon' => 'heroicon-o-users',
                'resource' => PegawaiResource::class,
            ],
            [
                'label' => 'Kenaikan Pangkat',
                'description' => 'Usulan dan status',
                'icon' => 'heroicon-o-arrow-trending-up',
                'resource' => KenaikanPangkatResource::class,
            ],
            [
                'label' => 'Gaji Berkala',
                'description' => 'Jadwal KGB',
                'icon' => 'heroicon-o-banknotes',
                'resource' => GajiBerkalaResource::class,
            ],
            [
                'label' => 'Legalisir',
                'description' => 'Permohonan layanan',
                'icon' => 'heroicon-o-document-check',
                'resource' => LegalisirResource::class,
            ],
        ];

        $modules = collect($moduleDefinitions)
            ->filter(fn (array $module): bool => $module['resource']::canViewAny())
            ->map(function (array $module): array {
                $module['url'] = $module['resource']::getUrl();
                unset($module['resource']);

                return $module;
            })
            ->values()
            ->all();

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

        $attention = [];

        if ($canViewLegalisir) {
            $attention[] = [
                'label' => 'Legalisir menunggu proses',
                'count' => Legalisir::perluTindakLanjut()->count(),
                'detail' => 'permohonan perlu diverifikasi',
                'url' => LegalisirResource::getUrl('index', [
                    'tableFilters' => ['perlu_tindak_lanjut' => ['isActive' => true]],
                ]),
                'tone' => 'amber',
            ];
        }

        if ($canViewSurat) {
            $overdueDispositions = Disposisi::terlambat()->count();
            if ($overdueDispositions > 0) {
                $attention[] = [
                    'label' => 'Disposisi melewati tenggat',
                    'count' => $overdueDispositions,
                    'detail' => 'tindak lanjut surat perlu perhatian',
                    'url' => SuratMasukResource::getUrl('index'),
                    'tone' => 'rose',
                ];
            }
        }

        if ($canViewKepegawaian) {
            $attention[] = [
                'label' => 'KGB mendekati jatuh tempo',
                'count' => GajiBerkala::perluTindakLanjut()->count(),
                'detail' => 'belum diserahkan; satu bulan lalu sampai tiga bulan ke depan',
                'url' => GajiBerkalaResource::getUrl('index', [
                    'tableFilters' => ['perlu_tindak_lanjut' => ['isActive' => true]],
                ]),
                'tone' => 'rose',
            ];
            $attention[] = [
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
            ];
        }

        return [
            'suratMasuk' => $suratMasuk,
            'suratKeluar' => $suratKeluar,
            'modules' => $modules,
            'utilities' => $utilities,
            'attention' => $attention,
            'canViewSurat' => $canViewSurat,
            'canCreateSuratMasuk' => SuratMasukResource::canCreate(),
            'canCreateSuratKeluar' => SuratKeluarResource::canCreate(),
            'roleLabel' => $user?->roleLabel() ?? 'Pengguna',
        ];
    }
}
