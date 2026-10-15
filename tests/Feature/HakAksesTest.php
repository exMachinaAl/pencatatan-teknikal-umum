<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HakAksesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hak_akses_melihat_gaji_hanya_admin_dan_kepala(): void
    {
        $admin = User::create([
            'name' => 'Admin TU',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $kepala = User::create([
            'name' => 'Kepala Sekolah',
            'email' => 'kepala@test.com',
            'password' => bcrypt('password'),
            'role' => 'kepala',
        ]);

        $staf = User::create([
            'name' => 'Staf TU',
            'email' => 'staf@test.com',
            'password' => bcrypt('password'),
            'role' => 'staf',
        ]);

        $pegawai = User::create([
            'name' => 'Guru',
            'email' => 'pegawai@test.com',
            'password' => bcrypt('password'),
            'role' => 'pegawai',
        ]);

        // Admin dan Kepala dapat melihat data gaji (UU PDP)
        $this->assertTrue($admin->canViewSalary());
        $this->assertTrue($kepala->canViewSalary());

        // Staf dan Pegawai umum dibatasi
        $this->assertFalse($staf->canViewSalary());
        $this->assertFalse($pegawai->canViewSalary());
    }

    public function test_kelola_pengguna_hanya_bisa_oleh_admin(): void
    {
        $admin = User::create([
            'name' => 'Admin TU',
            'email' => 'admin2@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $staf = User::create([
            'name' => 'Staf TU',
            'email' => 'staf2@test.com',
            'password' => bcrypt('password'),
            'role' => 'staf',
        ]);

        $this->assertTrue($admin->canManageUsers());
        $this->assertFalse($staf->canManageUsers());
    }
}
