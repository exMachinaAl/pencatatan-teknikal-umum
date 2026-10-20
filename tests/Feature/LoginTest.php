<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\Pegawai;
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
        $response->assertSee('Username atau NIP');
        $response->assertSee('Kata sandi');
        $response->assertSee('Hubungi administrator sekolah.');
    }

    public function test_form_login_menerima_akun_aktif(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::create([
            'name' => 'Operator', 'username' => 'operator',
            'password' => 'password', 'role' => User::ROLE_PETUGAS_TU, 'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'OPERATOR', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_form_login_menolak_kata_sandi_salah_dan_akun_nonaktif(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::create([
            'name' => 'Operator', 'username' => 'operator', 'email' => 'operator@test.com',
            'password' => 'password', 'role' => User::ROLE_PETUGAS_TU, 'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm(['login' => $user->username, 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['login'])
            ->assertSee('Tidak dapat masuk.');

        $this->assertGuest();
        $user->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->fillForm(['login' => $user->username, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['login'])
            ->assertSee('Tidak dapat masuk.');

        $this->assertGuest();
    }

    public function test_form_login_memberikan_validasi_yang_jelas(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['login' => '', 'password' => ''])
            ->call('authenticate')
            ->assertHasFormErrors(['login' => 'required', 'password' => 'required'])
            ->assertSee('Masukkan username, NIP, atau email Anda.')
            ->assertSee('Masukkan kata sandi Anda.');
    }

    public function test_form_login_menerima_nip_pegawai_dan_email_lama(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $pegawai = Pegawai::create([
            'nip' => '198001012005011001',
            'nama' => 'Operator Ber-NIP',
            'status_kepegawaian' => 'PNS',
        ]);
        $user = User::create([
            'pegawai_id' => $pegawai->id,
            'name' => 'Operator Ber-NIP',
            'username' => 'operator.nip',
            'email' => 'operator.nip@test.com',
            'password' => 'password',
            'role' => User::ROLE_PETUGAS_TU,
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm(['login' => '198001012005011001', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        Livewire::test(Login::class)
            ->fillForm(['login' => 'OPERATOR.NIP@TEST.COM', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_bisa_login(): void
    {
        $user = User::create([
            'name' => 'Operator TU',
            'username' => 'admin',
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
            ->assertSee('Administrator Sistem (Fasilitator TI)')
            ->assertSee('Pengaturan dan utilitas')
            ->assertSee('Surat yang perlu dilihat');
    }
}
