<?php

namespace Tests\Feature;

use App\Filament\Resources\AppSettingResource\Pages\ListAppSettings;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Filament\Resources\GajiBerkalaResource\Pages\ListGajiBerkalas;
use App\Filament\Resources\KenaikanPangkatResource\Pages\ListKenaikanPangkats;
use App\Filament\Resources\LegalisirResource\Pages\ListLegalisirs;
use App\Filament\Resources\PegawaiResource\Pages\ListPegawais;
use App\Filament\Resources\SuratKeluarResource\Pages\ListSuratKeluars;
use App\Filament\Resources\SuratMasukResource\Pages\ListSuratMasuks;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavigationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_query_pencarian_setiap_halaman_daftar_tidak_merusak_render_livewire(): void
    {
        $pagesByRole = [
            User::ROLE_PETUGAS_TU => [
                ListSuratMasuks::class,
                ListSuratKeluars::class,
                ListLegalisirs::class,
            ],
            User::ROLE_KEPEGAWAIAN => [
                ListPegawais::class,
                ListKenaikanPangkats::class,
                ListGajiBerkalas::class,
            ],
            User::ROLE_ADMIN => [
                ListUsers::class,
                ListAppSettings::class,
                ListAuditLogs::class,
            ],
        ];

        foreach ($pagesByRole as $role => $pages) {
            $this->actingAs($this->user($role));

            foreach ($pages as $page) {
                Livewire::test($page)
                    ->set('tableSearch', 'query-yang-tidak-ada-123')
                    ->assertOk()
                    ->assertHasNoErrors()
                    ->set('tableSearch', '')
                    ->assertOk()
                    ->assertHasNoErrors();
            }
        }
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => User::ROLE_LABELS[$role],
            'email' => $role.uniqid().'@navigation.test',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
