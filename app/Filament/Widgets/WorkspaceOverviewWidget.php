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
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
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
            ->with('klasifikasi')
            ->whereIn('status', ['baru', 'didisposisi'])
            ->orderByRaw("CASE WHEN sifat = 'segera' THEN 0 WHEN sifat = 'penting' THEN 1 ELSE 2 END")
            ->latest('tanggal_diterima')
            ->latest('id')
            ->first() : null;

        $suratKeluar = $canViewSurat ? SuratKeluar::query()
            ->with('klasifikasi')
            ->where('status', 'draf')
            ->orderByRaw("CASE WHEN sifat = 'segera' THEN 0 WHEN sifat = 'penting' THEN 1 ELSE 2 END")
            ->latest('tanggal_surat')
            ->latest('id')
            ->first() : null;

        $moduleDefinitions = [
            [
                'label' => 'Surat Masuk',
                'description' => 'Pencatatan, disposisi, dan arsip',
                'icon' => 'heroicon-o-inbox-arrow-down',
                'resource' => SuratMasukResource::class,
            ],
            [
                'label' => 'Surat Keluar',
                'description' => 'Penerbitan, pengiriman, dan arsip',
                'icon' => 'heroicon-o-paper-airplane',
                'resource' => SuratKeluarResource::class,
            ],
            [
                'label' => 'Pegawai',
                'description' => 'Identitas dan golongan pegawai',
                'icon' => 'heroicon-o-users',
                'resource' => PegawaiResource::class,
            ],
            [
                'label' => 'Kenaikan Pangkat',
                'description' => 'Pengajuan dan tahapan status',
                'icon' => 'heroicon-o-arrow-trending-up',
                'resource' => KenaikanPangkatResource::class,
            ],
            [
                'label' => 'Gaji Berkala',
                'description' => 'Pengajuan dan tahapan status',
                'icon' => 'heroicon-o-banknotes',
                'resource' => GajiBerkalaResource::class,
            ],
            [
                'label' => 'Legalisir',
                'description' => 'Permohonan, verifikasi, serah terima',
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
            $pendingLegalisir = Legalisir::perluTindakLanjut()->count();
            if ($pendingLegalisir > 0) {
                $attention[] = [
                    'label' => 'Legalisir belum selesai diverifikasi',
                    'count' => $pendingLegalisir,
                    'detail' => 'status diterima atau sedang diproses',
                    'url' => LegalisirResource::getUrl('index', [
                        'tableFilters' => ['perlu_tindak_lanjut' => ['isActive' => true]],
                    ]),
                    'tone' => 'amber',
                ];
            }

            $readyLegalisir = Legalisir::where('status', 'siap_diambil')->count();
            if ($readyLegalisir > 0) {
                $attention[] = [
                    'label' => 'Legalisir menunggu diserahkan',
                    'count' => $readyLegalisir,
                    'detail' => 'dokumen sudah siap dan belum diambil',
                    'url' => LegalisirResource::getUrl('index', [
                        'tableFilters' => ['status' => ['value' => 'siap_diambil']],
                    ]),
                    'tone' => 'emerald',
                ];
            }
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
            $activeKp = KenaikanPangkat::where('status', '!=', 'diserahkan')->count();
            if ($activeKp > 0) {
                $attention[] = [
                    'label' => 'Kenaikan pangkat masih berproses',
                    'count' => $activeKp,
                    'detail' => 'berkas belum sampai tahap diserahkan',
                    'url' => KenaikanPangkatResource::getUrl('index'),
                    'tone' => 'amber',
                ];
            }

            $activeKgb = GajiBerkala::where('status', '!=', 'diserahkan')->count();
            if ($activeKgb > 0) {
                $attention[] = [
                    'label' => 'KGB masih berproses',
                    'count' => $activeKgb,
                    'detail' => 'pengajuan belum sampai tahap diserahkan',
                    'url' => GajiBerkalaResource::getUrl('index', [
                        'tableFilters' => ['proses_aktif' => ['isActive' => true]],
                    ]),
                    'tone' => 'rose',
                ];
            }
        }

        $workflows = [];
        if ($canViewSurat) {
            $workflows[] = $this->workflow(
                'Surat Masuk',
                'Dari pencatatan sampai arsip',
                SuratMasukResource::class,
                SuratMasuk::class,
                SuratMasuk::STATUSES,
            );
            $workflows[] = $this->workflow(
                'Surat Keluar',
                'Dari konsep sampai arsip',
                SuratKeluarResource::class,
                SuratKeluar::class,
                SuratKeluar::STATUSES,
            );
        }
        if ($canViewLegalisir) {
            $workflows[] = $this->workflow(
                'Legalisir',
                'Permohonan, verifikasi, kesiapan, dan serah terima',
                LegalisirResource::class,
                Legalisir::class,
                Legalisir::STATUSES,
                ['ditolak'],
            );
        }
        if ($canViewKepegawaian) {
            $workflows[] = $this->workflow(
                'Kenaikan Pangkat',
                'Berkas disiapkan sampai diserahkan',
                KenaikanPangkatResource::class,
                KenaikanPangkat::class,
                KenaikanPangkat::STATUSES,
            );
            $workflows[] = $this->workflow(
                'Gaji Berkala',
                'Diproses sampai diserahkan',
                GajiBerkalaResource::class,
                GajiBerkala::class,
                GajiBerkala::STATUSES,
            );
        }

        return [
            'suratMasuk' => $suratMasuk,
            'suratKeluar' => $suratKeluar,
            'modules' => $modules,
            'utilities' => $utilities,
            'attention' => $attention,
            'workflows' => $workflows,
            'canViewSurat' => $canViewSurat,
            'canCreateSuratMasuk' => SuratMasukResource::canCreate(),
            'canCreateSuratKeluar' => SuratKeluarResource::canCreate(),
            'roleLabel' => $user?->roleLabel() ?? 'Pengguna',
        ];
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $model
     * @param  array<string, string>  $statuses
     * @param  array<int, string>  $branches
     */
    private function workflow(
        string $label,
        string $description,
        string $resource,
        string $model,
        array $statuses,
        array $branches = [],
    ): array {
        $counts = $model::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'label' => $label,
            'description' => $description,
            'url' => $resource::getUrl(),
            'stages' => collect($statuses)->map(fn (string $stageLabel, string $status): array => [
                'label' => $stageLabel,
                'count' => (int) ($counts[$status] ?? 0),
                'branch' => in_array($status, $branches, true),
                'url' => $resource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => $status]],
                ]),
            ])->values()->all(),
        ];
    }
}
