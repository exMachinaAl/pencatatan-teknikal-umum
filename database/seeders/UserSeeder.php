<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    private function initialPassword(string $username): string
    {
        $key = match ($username) {
            'admin' => 'TU_ADMIN_SISTEM_INITIAL_PASSWORD',
            'kepala_tu' => 'TU_KEPALA_TU_INITIAL_PASSWORD',
            'kepala_sekolah' => 'TU_KEPALA_SEKOLAH_INITIAL_PASSWORD',
            'staf_tu' => 'TU_PETUGAS_TU_INITIAL_PASSWORD',
            'pegawai' => 'TU_PEGAWAI_INITIAL_PASSWORD',
        };
        $password = trim((string) config("security.initial_passwords.{$username}"));

        if ($password === '' && app()->environment('production')) {
            throw new \RuntimeException("{$key} wajib diisi dengan password unik sebelum menjalankan seeder production.");
        }

        if (app()->environment('production') && mb_strlen($password) < 12) {
            throw new \RuntimeException("{$key} wajib berisi minimal 12 karakter pada production.");
        }

        return $password !== '' ? $password : 'password';
    }

    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator Sistem (Divisi IT)',
                'username' => 'admin',
                'email' => 'admin@tu.sekolah.sch.id',
                'nomor_telepon' => '081200000001',
                'jabatan' => 'Administrator Infrastruktur',
                'unit_kerja' => 'Divisi IT',
                'password' => $this->initialPassword('admin'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
            [
                'name' => 'Kepala Tata Usaha',
                'username' => 'kepala_tu',
                'email' => 'kepala.tu@tu.sekolah.sch.id',
                'nomor_telepon' => '081200000002',
                'jabatan' => 'Kepala Tata Usaha',
                'unit_kerja' => 'Tata Usaha',
                'password' => $this->initialPassword('kepala_tu'),
                'role' => User::ROLE_KEPALA_TU,
                'is_active' => true,
            ],
            [
                'name' => 'Drs. H. Mulyadi, M.Pd (Kepala Sekolah)',
                'username' => 'kepala_sekolah',
                'email' => 'kepala@tu.sekolah.sch.id',
                'nomor_telepon' => '081200000003',
                'jabatan' => 'Kepala Sekolah',
                'unit_kerja' => 'Pimpinan Sekolah',
                'password' => $this->initialPassword('kepala_sekolah'),
                'role' => User::ROLE_KEPALA_SEKOLAH,
                'is_active' => true,
            ],
            [
                'name' => 'Siti Rahmawati, A.Md (Staf TU)',
                'username' => 'staf_tu',
                'email' => 'staf@tu.sekolah.sch.id',
                'nomor_telepon' => '081200000004',
                'jabatan' => 'Staf Administrasi Persuratan',
                'unit_kerja' => 'Tata Usaha',
                'password' => $this->initialPassword('staf_tu'),
                'role' => User::ROLE_PETUGAS_TU,
                'is_active' => true,
            ],
            [
                'name' => 'Budi Santoso, S.Pd (Pegawai / Guru)',
                'username' => 'pegawai',
                'email' => 'pegawai@tu.sekolah.sch.id',
                'nomor_telepon' => '081200000005',
                'jabatan' => 'Guru',
                'unit_kerja' => 'Tenaga Pendidik',
                'password' => $this->initialPassword('pegawai'),
                'role' => User::ROLE_PEGAWAI,
                'is_active' => true,
            ],
        ];

        if (app()->environment('production')) {
            $passwords = array_column($users, 'password');
            if (count(array_unique($passwords)) !== count($passwords)) {
                throw new \RuntimeException('Setiap akun bawaan production wajib memakai password awal yang berbeda.');
            }
        }

        foreach ($users as $data) {
            $existing = User::withTrashed()->where('email', $data['email'])->first();
            if ($existing) {
                foreach (['username', 'nomor_telepon', 'jabatan', 'unit_kerja'] as $field) {
                    if (blank($existing->{$field}) && filled($data[$field] ?? null)) {
                        $existing->{$field} = $data[$field];
                    }
                }
                if ($existing->isDirty()) {
                    $existing->save();
                }

                continue;
            }

            User::create($data);
        }
    }
}
