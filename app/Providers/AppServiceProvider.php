<?php

namespace App\Providers;

use App\Models\Disposisi;
use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\RelasiSurat;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\DocumentFileObserver;
use App\Observers\RelatedActivityObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'Pegawai' => Pegawai::class,
            'KenaikanPangkat' => KenaikanPangkat::class,
            'GajiBerkala' => GajiBerkala::class,
            'SuratMasuk' => SuratMasuk::class,
            'SuratKeluar' => SuratKeluar::class,
            'Legalisir' => Legalisir::class,
        ]);

        // Daftarkan Observer Audit Log otomatis
        Pegawai::observe(AuditObserver::class);
        KenaikanPangkat::observe(AuditObserver::class);
        GajiBerkala::observe(AuditObserver::class);
        SuratMasuk::observe(AuditObserver::class);
        SuratMasuk::observe(DocumentFileObserver::class);
        SuratKeluar::observe(AuditObserver::class);
        SuratKeluar::observe(DocumentFileObserver::class);
        Legalisir::observe(AuditObserver::class);
        Legalisir::observe(DocumentFileObserver::class);
        Disposisi::observe(RelatedActivityObserver::class);
        RelasiSurat::observe(RelatedActivityObserver::class);
        User::observe(AuditObserver::class);
    }
}
