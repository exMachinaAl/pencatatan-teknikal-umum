<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_melengkapi_identitas_tanpa_menimpa_password_role_atau_status_akun_lama(): void
    {
        $existing = User::create([
            'name' => 'Admin Existing',
            'email' => 'admin@tu.sekolah.sch.id',
            'password' => 'rahasia-yang-sudah-diganti',
            'role' => User::ROLE_AUDITOR,
            'is_active' => false,
        ]);

        $this->seed(UserSeeder::class);

        $existing->refresh();
        $this->assertTrue(Hash::check('rahasia-yang-sudah-diganti', $existing->password));
        $this->assertSame(User::ROLE_AUDITOR, $existing->role);
        $this->assertFalse($existing->is_active);
        $this->assertSame('admin', $existing->username);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseHas('users', [
            'email' => 'kepala.tu@tu.sekolah.sch.id',
            'role' => User::ROLE_KEPALA_TU,
        ]);
    }

    public function test_menjalankan_seeder_ulang_mempertahankan_password_yang_sudah_diganti(): void
    {
        $this->seed(UserSeeder::class);

        $kepala = User::where('email', 'kepala@tu.sekolah.sch.id')->firstOrFail();
        $kepala->update(['password' => 'password-baru-yang-aman']);

        $this->seed(UserSeeder::class);

        $this->assertTrue(Hash::check('password-baru-yang-aman', $kepala->refresh()->password));
        $this->assertDatabaseCount('users', 5);
    }

    public function test_akun_bawaan_lokal_memakai_fallback_password_pengembangan(): void
    {
        $this->seed(UserSeeder::class);

        foreach (['admin', 'kepala_tu', 'kepala_sekolah', 'staf_tu', 'pegawai'] as $username) {
            $user = User::where('username', $username)->firstOrFail();
            $this->assertTrue(Hash::check('password', $user->password), "Password {$username} tidak sesuai.");
        }
    }
}
