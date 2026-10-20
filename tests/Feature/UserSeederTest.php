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

    public function test_seeder_tidak_menimpa_password_role_atau_status_akun_yang_sudah_ada(): void
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

    public function test_menjalankan_seeder_ulang_tidak_mereset_password(): void
    {
        $this->seed(UserSeeder::class);

        $kepala = User::where('email', 'kepala@tu.sekolah.sch.id')->firstOrFail();
        $kepala->update(['password' => 'password-baru-yang-aman']);

        $this->seed(UserSeeder::class);

        $this->assertTrue(Hash::check('password-baru-yang-aman', $kepala->refresh()->password));
        $this->assertDatabaseCount('users', 5);
    }

    public function test_seeder_memakai_password_dan_username_masing_masing_role(): void
    {
        config()->set('app.initial_user_passwords', [
            'admin_sistem' => 'Admin-Aman-123',
            'kepala_tu' => 'Kepala-TU-123',
            'kepala_sekolah' => 'Kepala-Sekolah-123',
            'petugas_tu' => 'Petugas-TU-123',
            'pegawai' => 'Pegawai-Aman-123',
        ]);

        $this->seed(UserSeeder::class);

        $admin = User::where('username', 'admin')->firstOrFail();
        $kepalaTu = User::where('username', 'kepala_tu')->firstOrFail();
        $this->assertTrue(Hash::check('Admin-Aman-123', $admin->password));
        $this->assertTrue(Hash::check('Kepala-TU-123', $kepalaTu->password));
    }
}
