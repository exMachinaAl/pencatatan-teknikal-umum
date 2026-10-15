<?php

namespace App\Providers;

use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Observers\AuditObserver;
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
        // Daftarkan Observer Audit Log otomatis
        Pegawai::observe(AuditObserver::class);
        KenaikanPangkat::observe(AuditObserver::class);
        GajiBerkala::observe(AuditObserver::class);
        SuratMasuk::observe(AuditObserver::class);
        SuratKeluar::observe(AuditObserver::class);
        Legalisir::observe(AuditObserver::class);
        User::observe(AuditObserver::class);
    }
}
