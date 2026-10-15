<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Operator TU (Admin)',
                'email' => 'admin@tu.sekolah.sch.id',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'name' => 'Drs. H. Mulyadi, M.Pd (Kepala Sekolah)',
                'email' => 'kepala@tu.sekolah.sch.id',
                'password' => Hash::make('password'),
                'role' => 'kepala',
                'is_active' => true,
            ],
            [
                'name' => 'Siti Rahmawati, A.Md (Staf TU)',
                'email' => 'staf@tu.sekolah.sch.id',
                'password' => Hash::make('password'),
                'role' => 'staf',
                'is_active' => true,
            ],
            [
                'name' => 'Budi Santoso, S.Pd (Pegawai / Guru)',
                'email' => 'pegawai@tu.sekolah.sch.id',
                'password' => Hash::make('password'),
                'role' => 'pegawai',
                'is_active' => true,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(['email' => $data['email']], $data);
        }
    }
}
