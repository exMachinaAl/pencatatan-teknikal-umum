<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

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
                'password' => 'password',
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
                'password' => 'password',
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
                'password' => 'password',
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
                'password' => 'password',
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
                'password' => 'password',
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
                $existing->password = 'password';
                if ($existing->isDirty()) {
                    $existing->save();
                }

                continue;
            }

            User::create($data);
        }
    }
}
