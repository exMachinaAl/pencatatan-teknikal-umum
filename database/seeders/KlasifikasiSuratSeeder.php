<?php

namespace Database\Seeders;

use App\Models\RefKlasifikasiSurat;
use Illuminate\Database\Seeder;

class KlasifikasiSuratSeeder extends Seeder
{
    public function run(): void
    {
        $klasifikasi = [
            ['kode' => '421.1', 'nama' => 'Penyelenggaraan Sekolah / Administrasi Umum', 'retensi_tahun' => 5],
            ['kode' => '421.2', 'nama' => 'Kesiswaan, Ekstrakurikuler, & Beasiswa', 'retensi_tahun' => 5],
            ['kode' => '421.3', 'nama' => 'Kurikulum, Kalender Pendidikan, & Pembelajaran', 'retensi_tahun' => 5],
            ['kode' => '421.5', 'nama' => 'Kelulusan, Ijazah, & Legalisir Dokumen', 'retensi_tahun' => 10],
            ['kode' => '800', 'nama' => 'Kepegawaian (Kenaikan Pangkat, KGB, Mutasi, Cuti)', 'retensi_tahun' => 10],
            ['kode' => '005', 'nama' => 'Undangan Rapat / Dinas / Sosialisasi', 'retensi_tahun' => 2],
            ['kode' => '045', 'nama' => 'Penelitian, PKL, Magang, & Kunjungan Belajar', 'retensi_tahun' => 3],
            ['kode' => '900', 'nama' => 'Keuangan, Anggaran, & Pelaporan Dana BOS', 'retensi_tahun' => 10],
            ['kode' => '020', 'nama' => 'Sarana, Prasarana, & Pengadaan Barang/Jasa', 'retensi_tahun' => 5],
            ['kode' => '090', 'nama' => 'Perjalanan Dinas & Surat Perintah Tugas (SPT)', 'retensi_tahun' => 3],
        ];

        foreach ($klasifikasi as $item) {
            RefKlasifikasiSurat::firstOrCreate(['kode' => $item['kode']], $item);
        }
    }
}
