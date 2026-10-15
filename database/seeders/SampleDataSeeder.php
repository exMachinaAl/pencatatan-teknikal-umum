<?php

namespace Database\Seeders;

use App\Models\Disposisi;
use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $gol3a = RefGolongan::where('kode', 'III/a')->first();
        $gol3b = RefGolongan::where('kode', 'III/b')->first();
        $gol3c = RefGolongan::where('kode', 'III/c')->first();
        $gol4a = RefGolongan::where('kode', 'IV/a')->first();
        $golPppk9 = RefGolongan::where('kode', 'IX (PPPK)')->first();

        $klasUmum = RefKlasifikasiSurat::where('kode', '421.1')->first();
        $klasPegawai = RefKlasifikasiSurat::where('kode', '800')->first();
        $klasSiswa = RefKlasifikasiSurat::where('kode', '421.2')->first();
        $klasLegalisir = RefKlasifikasiSurat::where('kode', '421.5')->first();

        // 1. Data Pegawai
        $pegawaiList = [
            [
                'nip' => '19750815 200501 1 002',
                'nama' => 'Ahmad Fauzi, S.Pd., M.Si',
                'status_kepegawaian' => 'PNS',
                'jabatan' => 'Guru Matematika Ahli Madya',
                'golongan_id' => $gol4a?->id,
                'tmt_pangkat_terakhir' => Carbon::now()->subYears(4)->format('Y-m-d'), // Calon KP sekarang!
                'tmt_kgb_terakhir' => Carbon::now()->subYears(2)->addMonths(1)->format('Y-m-d'), // Jatuh tempo 1 bulan lagi!
                'is_active' => true,
            ],
            [
                'nip' => '19820320 200902 2 004',
                'nama' => 'Dewi Anggraeni, M.Pd',
                'status_kepegawaian' => 'PNS',
                'jabatan' => 'Guru Bahasa Indonesia Ahli Muda',
                'golongan_id' => $gol3c?->id,
                'tmt_pangkat_terakhir' => Carbon::now()->subYears(3)->format('Y-m-d'),
                'tmt_kgb_terakhir' => Carbon::now()->subYears(2)->addMonths(2)->format('Y-m-d'), // Jatuh tempo 2 bulan lagi!
                'is_active' => true,
            ],
            [
                'nip' => '19900412 201801 1 003',
                'nama' => 'Rian Hidayat, S.Kom',
                'status_kepegawaian' => 'PNS',
                'jabatan' => 'Guru Informatika / Operator TU',
                'golongan_id' => $gol3b?->id,
                'tmt_pangkat_terakhir' => Carbon::now()->subYears(4)->addMonths(2)->format('Y-m-d'), // Calon KP dalam 2 bulan
                'tmt_kgb_terakhir' => Carbon::now()->subYears(1)->format('Y-m-d'),
                'is_active' => true,
            ],
            [
                'nip' => '19951128 202221 2 009',
                'nama' => 'Nurul Halimah, S.Pd',
                'status_kepegawaian' => 'PPPK',
                'jabatan' => 'Guru Biologi Ahli Pertama',
                'golongan_id' => $golPppk9?->id,
                'tmt_pangkat_terakhir' => Carbon::now()->subYears(2)->format('Y-m-d'),
                'tmt_kgb_terakhir' => Carbon::now()->subYears(2)->addDays(15)->format('Y-m-d'), // Jatuh tempo sangat dekat!
                'is_active' => true,
            ],
            [
                'nip' => null,
                'nama' => 'Bambang Trianto',
                'status_kepegawaian' => 'Honorer',
                'jabatan' => 'Staff Administrasi / Arsiparis',
                'golongan_id' => null,
                'tmt_pangkat_terakhir' => null,
                'tmt_kgb_terakhir' => null,
                'is_active' => true,
            ],
        ];

        $createdPegawai = [];
        foreach ($pegawaiList as $p) {
            $createdPegawai[] = Pegawai::create($p);
        }

        // 2. Kenaikan Pangkat
        KenaikanPangkat::create([
            'pegawai_id' => $createdPegawai[0]->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $gol3c?->id,
            'golongan_baru_id' => $gol4a?->id,
            'periode' => Carbon::now()->format('Y-m'),
            'tmt' => Carbon::now()->addMonths(1)->startOfMonth()->format('Y-m-d'),
            'nomor_sk' => '823/045/BKD/2026',
            'tanggal_sk' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'pejabat_penetap' => 'Gubernur Jawa Barat',
            'status' => 'sk_terbit',
            'catatan' => 'SK sudah terbit, menunggu proses penyerahan resmi.',
            'created_by' => $admin?->id,
        ]);

        KenaikanPangkat::create([
            'pegawai_id' => $createdPegawai[2]->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $gol3a?->id,
            'golongan_baru_id' => $gol3b?->id,
            'periode' => Carbon::now()->format('Y-m'),
            'tmt' => Carbon::now()->addMonths(2)->startOfMonth()->format('Y-m-d'),
            'status' => 'diajukan',
            'catatan' => 'Berkas usulan telah dikirim ke Disdik Provinsi.',
            'created_by' => $admin?->id,
        ]);

        // 3. Gaji Berkala (KGB)
        GajiBerkala::create([
            'pegawai_id' => $createdPegawai[0]->id,
            'golongan_id' => $gol4a?->id,
            'tmt' => Carbon::now()->subYears(2)->format('Y-m-d'),
            'gaji_pokok_lama' => 3800000,
            'gaji_pokok_baru' => 4150000,
            'mk_tahun' => 14,
            'mk_bulan' => 0,
            'nomor_sk' => '822.3/089/Disdik/2024',
            'tanggal_sk' => Carbon::now()->subYears(2)->subDays(10)->format('Y-m-d'),
            'pejabat_penetap' => 'Kepala Dinas Pendidikan',
            'tmt_berikutnya' => Carbon::now()->addMonths(1)->startOfMonth()->format('Y-m-d'), // Jatuh tempo 1 bulan lagi
            'status' => 'diproses',
            'catatan' => 'Pemberitahuan KGB sudah disiapkan untuk penandatanganan.',
            'created_by' => $admin?->id,
        ]);

        GajiBerkala::create([
            'pegawai_id' => $createdPegawai[1]->id,
            'golongan_id' => $gol3c?->id,
            'tmt' => Carbon::now()->subYears(2)->format('Y-m-d'),
            'gaji_pokok_lama' => 3200000,
            'gaji_pokok_baru' => 3450000,
            'mk_tahun' => 10,
            'mk_bulan' => 0,
            'nomor_sk' => '822.3/120/Disdik/2024',
            'tanggal_sk' => Carbon::now()->subYears(2)->subDays(15)->format('Y-m-d'),
            'pejabat_penetap' => 'Kepala Dinas Pendidikan',
            'tmt_berikutnya' => Carbon::now()->addMonths(2)->startOfMonth()->format('Y-m-d'), // Jatuh tempo 2 bulan lagi
            'status' => 'diproses',
            'catatan' => 'Berkas riwayat gaji telah diverifikasi bendahara.',
            'created_by' => $admin?->id,
        ]);

        // 4. Surat Masuk & Disposisi
        $sm1 = SuratMasuk::create([
            'nomor_agenda' => SuratMasuk::generateNomorAgenda(),
            'nomor_surat' => '005/1432/Disdik/X/2026',
            'tanggal_surat' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'tanggal_diterima' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'pengirim' => 'Dinas Pendidikan Provinsi Jawa Barat',
            'perihal' => 'Undangan Rapat Koordinasi Persiapan Asesmen Nasional 2026',
            'sifat' => 'penting',
            'klasifikasi_id' => $klasUmum?->id,
            'status' => 'didisposisi',
            'lokasi_arsip_fisik' => 'Lemari A, Rak 2, Ordner Dinas 2026',
            'created_by' => $admin?->id,
        ]);

        Disposisi::create([
            'surat_masuk_id' => $sm1->id,
            'dari_user_id' => $admin?->id,
            'kepada' => 'Wakasek Kurikulum & Staf IT',
            'instruksi' => 'Harap menghadiri rapat koordinasi dan menyiapkan rekap data ruang lab komputer.',
            'batas_waktu' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'status' => 'diproses',
            'catatan_tindak_lanjut' => 'Sudah dikonfirmasi siap hadir oleh Pak Ahmad Fauzi.',
        ]);

        $sm2 = SuratMasuk::create([
            'nomor_agenda' => SuratMasuk::generateNomorAgenda(),
            'nomor_surat' => '420/221/Kec.Sukajadi/2026',
            'tanggal_surat' => Carbon::now()->subDays(1)->format('Y-m-d'),
            'tanggal_diterima' => Carbon::now()->format('Y-m-d'),
            'pengirim' => 'Kantor Kecamatan Sukajadi',
            'perihal' => 'Permohonan Partisipasi Petugas Upacara Hari Pahlawan',
            'sifat' => 'biasa',
            'klasifikasi_id' => $klasSiswa?->id,
            'status' => 'baru',
            'lokasi_arsip_fisik' => 'Map Surat Masuk Baru Meja TU',
            'created_by' => $admin?->id,
        ]);

        // 5. Surat Keluar
        $nomorSk1 = SuratKeluar::generateNomorSurat(null, '421.1');
        SuratKeluar::create([
            'nomor_surat' => $nomorSk1['nomor_surat'],
            'nomor_urut' => $nomorSk1['nomor_urut'],
            'tahun' => $nomorSk1['tahun'],
            'tanggal_surat' => Carbon::now()->subDays(1)->format('Y-m-d'),
            'tujuan' => 'Kepala Dinas Pendidikan Provinsi Jawa Barat',
            'perihal' => 'Laporan Rekapitulasi Kehadiran Guru dan Tenaga Kependidikan Bulan September 2026',
            'sifat' => 'biasa',
            'klasifikasi_id' => $klasPegawai?->id,
            'penandatangan' => 'Drs. H. Mulyadi, M.Pd',
            'pembuat_konsep' => 'Siti Rahmawati, A.Md',
            'status' => 'terkirim',
            'lokasi_arsip_fisik' => 'Boks Arsip Surat Keluar 2026',
            'created_by' => $admin?->id,
        ]);

        $nomorSk2 = SuratKeluar::generateNomorSurat(null, '421.2');
        SuratKeluar::create([
            'nomor_surat' => $nomorSk2['nomor_surat'],
            'nomor_urut' => $nomorSk2['nomor_urut'],
            'tahun' => $nomorSk2['tahun'],
            'tanggal_surat' => Carbon::now()->format('Y-m-d'),
            'tujuan' => 'Orang Tua / Wali Siswa Kelas XII',
            'perihal' => 'Surat Pemberitahuan Simulasi Ujian dan Sosialisasi SNBP 2027',
            'sifat' => 'penting',
            'klasifikasi_id' => $klasSiswa?->id,
            'penandatangan' => 'Drs. H. Mulyadi, M.Pd',
            'pembuat_konsep' => 'Rian Hidayat, S.Kom',
            'status' => 'draf',
            'created_by' => $admin?->id,
        ]);

        // 6. Legalisir
        Legalisir::create([
            'nomor_legalisir' => Legalisir::generateNomorLegalisir(),
            'nama_pemohon' => 'Muhammad Rizki Pratama',
            'kontak' => '081234567890',
            'status_pemohon' => 'alumni',
            'jenis_dokumen' => 'Ijazah',
            'jumlah_lembar' => 5,
            'tanggal_permohonan' => Carbon::now()->subDays(1)->format('Y-m-d'),
            'tanggal_selesai' => Carbon::now()->format('Y-m-d'),
            'biaya' => 0,
            'status' => 'siap_diambil',
            'petugas_id' => $admin?->id,
            'catatan' => 'Lulusan tahun 2023, buku induk sudah dicocokkan.',
        ]);

        Legalisir::create([
            'nomor_legalisir' => Legalisir::generateNomorLegalisir(),
            'nama_pemohon' => 'Anisa Maharani',
            'kontak' => '085799881122',
            'status_pemohon' => 'alumni',
            'jenis_dokumen' => 'Transkrip Nilai',
            'jumlah_lembar' => 3,
            'tanggal_permohonan' => Carbon::now()->format('Y-m-d'),
            'biaya' => 0,
            'status' => 'diterima',
            'petugas_id' => $admin?->id,
            'catatan' => 'Permohonan untuk pendaftaran beasiswa pascasarjana.',
        ]);
    }
}
