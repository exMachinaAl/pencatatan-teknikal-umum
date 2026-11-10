<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoUserSeeder dilewati di luar lingkungan local/testing.');

            return;
        }

        $users = [
            ['name' => 'Kepala Tata Usaha', 'username' => 'kepala_tu', 'email' => 'kepala.tu@tu.sekolah.sch.id', 'nomor_telepon' => '081200000002', 'jabatan' => 'Kepala Tata Usaha', 'unit_kerja' => 'Tata Usaha', 'role' => User::ROLE_KEPALA_TU],
            ['name' => 'Drs. H. Mulyadi, M.Pd (Kepala Sekolah)', 'username' => 'kepala_sekolah', 'email' => 'kepala@tu.sekolah.sch.id', 'nomor_telepon' => '081200000003', 'jabatan' => 'Kepala Sekolah', 'unit_kerja' => 'Pimpinan Sekolah', 'role' => User::ROLE_KEPALA_SEKOLAH],
            ['name' => 'Siti Rahmawati, A.Md (Staf TU)', 'username' => 'staf_tu', 'email' => 'staf@tu.sekolah.sch.id', 'nomor_telepon' => '081200000004', 'jabatan' => 'Staf Administrasi Persuratan', 'unit_kerja' => 'Tata Usaha', 'role' => User::ROLE_PETUGAS_TU],
            ['name' => 'Dewi Lestari, S.AP (Petugas Kepegawaian)', 'username' => 'kepegawaian', 'email' => 'kepegawaian@tu.sekolah.sch.id', 'nomor_telepon' => '081200000006', 'jabatan' => 'Pengelola Administrasi Kepegawaian', 'unit_kerja' => 'Tata Usaha', 'role' => User::ROLE_KEPEGAWAIAN],
            ['name' => 'Pengawas Internal Sekolah', 'username' => 'auditor', 'email' => 'auditor@tu.sekolah.sch.id', 'nomor_telepon' => '081200000007', 'jabatan' => 'Pengawas / Auditor Internal', 'unit_kerja' => 'Pengawasan', 'role' => User::ROLE_AUDITOR],
            ['name' => 'Budi Santoso, S.Pd (Pegawai / Guru)', 'username' => 'pegawai', 'email' => 'pegawai@tu.sekolah.sch.id', 'nomor_telepon' => '081200000005', 'jabatan' => 'Guru', 'unit_kerja' => 'Tenaga Pendidik', 'role' => User::ROLE_PEGAWAI],
        ];

        foreach ($users as $data) {
            $existing = User::withTrashed()
                ->where('username', $data['username'])
                ->orWhere('email', $data['email'])
                ->first();

            if ($existing) {
                continue;
            }

            User::create([
                ...$data,
                'password' => self::DEMO_PASSWORD,
                'is_active' => true,
            ]);
        }

        $this->command?->info('Akun demo seluruh role tersedia dengan password khusus development.');
    }
}
