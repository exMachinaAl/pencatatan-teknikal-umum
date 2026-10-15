<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_bisa_diakses(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('Sistem Informasi Tata Usaha');
        $response->assertSee('Masuk ke ruang kerja');
        $response->assertSee('Alamat email');
        $response->assertSee('Kata sandi');
        $response->assertSee('Hubungi administrator sekolah.');
    }

    public function test_form_login_menerima_akun_aktif(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::create([
            'name' => 'Operator', 'email' => 'operator@test.com',
            'password' => 'password', 'role' => 'staf', 'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_form_login_menolak_kata_sandi_salah_dan_akun_nonaktif(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::create([
            'name' => 'Operator', 'email' => 'operator@test.com',
            'password' => 'password', 'role' => 'staf', 'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['email'])
            ->assertSee('Tidak dapat masuk.');

        $this->assertGuest();
        $user->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email'])
            ->assertSee('Tidak dapat masuk.');

        $this->assertGuest();
    }

    public function test_form_login_memberikan_validasi_yang_jelas(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => '', 'password' => ''])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => 'required', 'password' => 'required'])
            ->assertSee('Masukkan alamat email Anda.')
            ->assertSee('Masukkan kata sandi Anda.');
    }

    public function test_admin_bisa_login(): void
    {
        $user = User::create([
            'name' => 'Operator TU',
            'email' => 'admin@tu.sekolah.sch.id',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin');
        $response
            ->assertStatus(200)
            ->assertSee('Ruang Kerja Tata Usaha')
            ->assertSee('Pilih ruang kerja')
            ->assertSee('Surat yang perlu dilihat');
    }
}
