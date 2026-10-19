<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HakAksesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hak_akses_gaji_hanya_kepala_tu_dan_petugas_kepegawaian(): void
    {
        $admin = User::create([
            'name' => 'Administrator Sistem',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $kepala = User::create([
            'name' => 'Kepala Sekolah',
            'email' => 'kepala@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_KEPALA_TU,
        ]);

        $staf = User::create([
            'name' => 'Staf TU',
            'email' => 'staf@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS_TU,
        ]);

        $pegawai = User::create([
            'name' => 'Guru',
            'email' => 'pegawai@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PEGAWAI,
        ]);

        $kepegawaian = User::create([
            'name' => 'Petugas Kepegawaian',
            'email' => 'kepegawaian@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_KEPEGAWAIAN,
        ]);

        // Admin teknis tidak otomatis memperoleh akses data gaji.
        $this->assertFalse($admin->canViewSalary());
        $this->assertTrue($kepala->canViewSalary());
        $this->assertTrue($kepegawaian->canViewSalary());

        // Staf dan Pegawai umum dibatasi
        $this->assertFalse($staf->canViewSalary());
        $this->assertFalse($pegawai->canViewSalary());
    }

    public function test_kelola_pengguna_hanya_bisa_oleh_admin(): void
    {
        $admin = User::create([
            'name' => 'Administrator Sistem',
            'email' => 'admin2@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $staf = User::create([
            'name' => 'Staf TU',
            'email' => 'staf2@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS_TU,
        ]);

        $this->assertTrue($admin->canManageUsers());
        $this->assertFalse($staf->canManageUsers());
    }
}
