<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
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
                'password_key' => 'admin_sistem',
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
                'password_key' => 'kepala_tu',
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
                'password_key' => 'kepala_sekolah',
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
                'password_key' => 'petugas_tu',
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
                'password_key' => 'pegawai',
                'role' => User::ROLE_PEGAWAI,
                'is_active' => true,
            ],
        ];

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

            $passwordKey = $data['password_key'];
            unset($data['password_key']);
            $data['password'] = $this->initialPassword($passwordKey);

            User::create($data);
        }
    }

    private function initialPassword(string $passwordKey): string
    {
        $environmentKey = 'TU_'.strtoupper($passwordKey).'_INITIAL_PASSWORD';
        $password = trim((string) config("app.initial_user_passwords.{$passwordKey}", ''));

        if ($password !== '') {
            if (mb_strlen($password) < 12) {
                throw new RuntimeException("{$environmentKey} minimal 12 karakter.");
            }

            return $password;
        }

        if (app()->environment('production')) {
            throw new RuntimeException("{$environmentKey} wajib diisi sebelum membuat akun awal di production.");
        }

        return 'password';
    }
}
