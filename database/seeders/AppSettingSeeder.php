<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'nama_sekolah',
                'value' => 'SMAN 1 BINA BANGSA',
                'deskripsi' => 'Nama resmi instansi sekolah negeri',
            ],
            [
                'key' => 'alamat_sekolah',
                'value' => 'Jl. Pemuda Pendidikan No. 45, Kecamatan Sukajadi, Kota Bandung, Jawa Barat',
                'deskripsi' => 'Alamat lengkap untuk kop surat dan bukti legalisir',
            ],
            [
                'key' => 'telepon_sekolah',
                'value' => '(022) 7654321',
                'deskripsi' => 'Nomor telepon resmi sekolah',
            ],
            [
                'key' => 'email_sekolah',
                'value' => 'tu@sman1binabangsa.sch.id',
                'deskripsi' => 'Alamat email dinas tata usaha sekolah',
            ],
            [
                'key' => 'kepala_sekolah',
                'value' => 'Drs. H. Mulyadi, M.Pd',
                'deskripsi' => 'Nama Kepala Sekolah yang berwenang bertandatangan',
            ],
            [
                'key' => 'nip_kepala_sekolah',
                'value' => '19680512 199303 1 004',
                'deskripsi' => 'NIP Kepala Sekolah',
            ],
            [
                'key' => 'periode_kp_standar_tahun',
                'value' => '4',
                'deskripsi' => 'Standar interval tahun untuk kenaikan pangkat reguler',
            ],
            [
                'key' => 'periode_kgb_standar_tahun',
                'value' => '2',
                'deskripsi' => 'Standar interval tahun untuk kenaikan gaji berkala (KGB)',
            ],
        ];

        foreach ($settings as $setting) {
            AppSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
