<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
use App\Models\NotificationOutbox;
use App\Models\Pegawai;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Policies\AppSettingPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\GajiBerkalaPolicy;
use App\Policies\KenaikanPangkatPolicy;
use App\Policies\LegalisirPolicy;
use App\Policies\NotificationOutboxPolicy;
use App\Policies\PegawaiPolicy;
use App\Policies\SuratKeluarPolicy;
use App\Policies\SuratMasukPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        AppSetting::class => AppSettingPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        GajiBerkala::class => GajiBerkalaPolicy::class,
        KenaikanPangkat::class => KenaikanPangkatPolicy::class,
        Legalisir::class => LegalisirPolicy::class,
        NotificationOutbox::class => NotificationOutboxPolicy::class,
        Pegawai::class => PegawaiPolicy::class,
        SuratKeluar::class => SuratKeluarPolicy::class,
        SuratMasuk::class => SuratMasukPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
