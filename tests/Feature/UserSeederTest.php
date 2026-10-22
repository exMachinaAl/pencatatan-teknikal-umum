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

    public function test_seeder_mereset_password_tetapi_tidak_menimpa_role_atau_status_akun_yang_sudah_ada(): void
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
        $this->assertTrue(Hash::check('password', $existing->password));
        $this->assertSame(User::ROLE_AUDITOR, $existing->role);
        $this->assertFalse($existing->is_active);
        $this->assertSame('admin', $existing->username);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseHas('users', [
            'email' => 'kepala.tu@tu.sekolah.sch.id',
            'role' => User::ROLE_KEPALA_TU,
        ]);
    }

    public function test_menjalankan_seeder_ulang_mereset_password(): void
    {
        $this->seed(UserSeeder::class);

        $kepala = User::where('email', 'kepala@tu.sekolah.sch.id')->firstOrFail();
        $kepala->update(['password' => 'password-baru-yang-aman']);

        $this->seed(UserSeeder::class);

        $this->assertTrue(Hash::check('password', $kepala->refresh()->password));
        $this->assertDatabaseCount('users', 5);
    }

    public function test_seluruh_akun_bawaan_memakai_password_yang_sama(): void
    {
        $this->seed(UserSeeder::class);

        foreach (['admin', 'kepala_tu', 'kepala_sekolah', 'staf_tu', 'pegawai'] as $username) {
            $user = User::where('username', $username)->firstOrFail();
            $this->assertTrue(Hash::check('password', $user->password), "Password {$username} tidak sesuai.");
        }
    }
}
