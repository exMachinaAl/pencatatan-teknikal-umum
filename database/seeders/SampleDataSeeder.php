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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('SampleDataSeeder dilewati di luar lingkungan local/testing.');

            return;
        }

        DB::transaction(function (): void {
            $accounts = $this->seedAdditionalAccounts();
            $employees = $this->seedEmployees();

            $this->linkEmployeeLogin($employees['rian']);
            $this->seedPersonnelWorkflows($employees, $accounts);
            $this->seedCorrespondence($accounts);
            $this->seedLegalisir($accounts);
        }, 3);

        $this->command?->info('Data contoh terbaru tersedia dan aman dijalankan ulang.');
    }

    /**
     * Menambah akun kedua untuk menguji bahwa transaksi dibedakan per akun,
     * bukan hanya berdasarkan role yang sama.
     *
     * @return array<string, User>
     */
    private function seedAdditionalAccounts(): array
    {
        $petugasUtama = User::where('username', 'staf_tu')->firstOrFail();
        $kepegawaianUtama = User::where('username', 'kepegawaian')->firstOrFail();

        $petugasKedua = User::withTrashed()->firstOrCreate(
            ['username' => 'staf_tu_2'],
            [
                'name' => 'Rina Kurniasih (Staf TU 2)',
                'email' => 'staf2@tu.sekolah.sch.id',
                'nomor_telepon' => '081200000008',
                'jabatan' => 'Staf Administrasi Legalisir',
                'unit_kerja' => 'Tata Usaha',
                'password' => 'password',
                'role' => User::ROLE_PETUGAS_TU,
                'is_active' => true,
            ],
        );
        $kepegawaianKedua = User::withTrashed()->firstOrCreate(
            ['username' => 'kepegawaian_2'],
            [
                'name' => 'Arif Nugraha (Kepegawaian 2)',
                'email' => 'kepegawaian2@tu.sekolah.sch.id',
                'nomor_telepon' => '081200000009',
                'jabatan' => 'Staf Administrasi Kepegawaian',
                'unit_kerja' => 'Tata Usaha',
                'password' => 'password',
                'role' => User::ROLE_KEPEGAWAIAN,
                'is_active' => true,
            ],
        );

        return [
            'petugas_utama' => $petugasUtama,
            'petugas_kedua' => $petugasKedua,
            'kepegawaian_utama' => $kepegawaianUtama,
            'kepegawaian_kedua' => $kepegawaianKedua,
        ];
    }

    /** @return array<string, Pegawai> */
    private function seedEmployees(): array
    {
        $gol3a = RefGolongan::where('kode', 'III/a')->firstOrFail();
        $gol3b = RefGolongan::where('kode', 'III/b')->firstOrFail();
        $gol3c = RefGolongan::where('kode', 'III/c')->firstOrFail();
        $golPppk9 = RefGolongan::where('kode', 'IX (PPPK)')->firstOrFail();

        return [
            'ahmad' => Pegawai::firstOrCreate(
                ['nip' => '197508152005011002'],
                ['nama' => 'Ahmad Fauzi, S.Pd., M.Si', 'status_kepegawaian' => 'PNS', 'golongan_id' => $gol3c->id],
            ),
            'dewi' => Pegawai::firstOrCreate(
                ['nip' => '198203202009022004'],
                ['nama' => 'Dewi Anggraeni, M.Pd', 'status_kepegawaian' => 'PNS', 'golongan_id' => $gol3c->id],
            ),
            'rian' => Pegawai::firstOrCreate(
                ['nip' => '199004122018011003'],
                ['nama' => 'Rian Hidayat, S.Kom', 'status_kepegawaian' => 'PNS', 'golongan_id' => $gol3a->id],
            ),
            'nurul' => Pegawai::firstOrCreate(
                ['nip' => '199511282022212009'],
                ['nama' => 'Nurul Halimah, S.Pd', 'status_kepegawaian' => 'PPPK', 'golongan_id' => $golPppk9->id],
            ),
            'bambang' => Pegawai::firstOrCreate(
                ['nama' => 'Bambang Trianto'],
                ['nip' => null, 'status_kepegawaian' => 'Honorer', 'golongan_id' => null],
            ),
            'sari' => Pegawai::firstOrCreate(
                ['nip' => '198611052010012007'],
                ['nama' => 'Sari Puspitasari, S.Pd', 'status_kepegawaian' => 'PNS', 'golongan_id' => $gol3b->id],
            ),
        ];
    }

    private function linkEmployeeLogin(Pegawai $pegawai): void
    {
        $account = User::where('username', 'pegawai')->firstOrFail();
        if ($account->pegawai_id === null && ! User::withTrashed()->where('pegawai_id', $pegawai->id)->exists()) {
            $account->update(['pegawai_id' => $pegawai->id]);
        }
    }

    /**
     * @param  array<string, Pegawai>  $employees
     * @param  array<string, User>  $accounts
     */
    private function seedPersonnelWorkflows(array $employees, array $accounts): void
    {
        $gol3a = RefGolongan::where('kode', 'III/a')->firstOrFail();
        $gol3b = RefGolongan::where('kode', 'III/b')->firstOrFail();
        $gol3c = RefGolongan::where('kode', 'III/c')->firstOrFail();
        $gol4a = RefGolongan::where('kode', 'IV/a')->firstOrFail();

        $this->firstOrCreateKp([
            'pegawai_id' => $employees['ahmad']->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $gol3c->id,
            'golongan_baru_id' => $gol4a->id,
            'mk_tahun' => 20,
            'mk_bulan' => 9,
            'tmt' => '2027-01-01',
            'tautan_lampiran' => 'https://drive.google.com/drive/folders/contoh-kp-ahmad',
            'status' => 'sk_terbit',
            'created_by' => $accounts['kepegawaian_utama']->id,
        ]);
        $this->firstOrCreateKp([
            'pegawai_id' => $employees['rian']->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $gol3a->id,
            'golongan_baru_id' => $gol3b->id,
            'mk_tahun' => 8,
            'mk_bulan' => 10,
            'tautan_lampiran' => null,
            'status' => 'diajukan',
            'created_by' => $accounts['kepegawaian_kedua']->id,
        ]);
        $this->firstOrCreateKp([
            'pegawai_id' => $employees['sari']->id,
            'jenis' => 'pilihan',
            'golongan_lama_id' => $gol3b->id,
            'golongan_baru_id' => $gol3c->id,
            'mk_tahun' => 12,
            'mk_bulan' => 2,
            'tautan_lampiran' => 'https://docs.google.com/spreadsheets/d/contoh-kp-sari',
            'status' => 'berkas_disiapkan',
            'created_by' => $accounts['kepegawaian_utama']->id,
        ]);

        $this->firstOrCreateKgb([
            'pegawai_id' => $employees['dewi']->id,
            'golongan_id' => $gol3c->id,
            'mk_tahun' => 17,
            'mk_bulan' => 8,
            'tautan_lampiran' => 'https://drive.google.com/drive/folders/contoh-kgb-dewi',
            'status' => 'sk_terbit',
            'created_by' => $accounts['kepegawaian_utama']->id,
        ]);
        $this->firstOrCreateKgb([
            'pegawai_id' => $employees['rian']->id,
            'golongan_id' => $gol3a->id,
            'mk_tahun' => 8,
            'mk_bulan' => 10,
            'tautan_lampiran' => null,
            'status' => 'diproses',
            'created_by' => $accounts['kepegawaian_kedua']->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function firstOrCreateKp(array $attributes): KenaikanPangkat
    {
        $key = KenaikanPangkat::integrityKey(
            $attributes['pegawai_id'],
            $attributes['jenis'],
            $attributes['tmt'] ?? null,
            $attributes['mk_tahun'],
            $attributes['mk_bulan'],
        );

        return KenaikanPangkat::withTrashed()->firstOrCreate(['integrity_key' => $key], $attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function firstOrCreateKgb(array $attributes): GajiBerkala
    {
        $key = GajiBerkala::integrityKey(
            $attributes['pegawai_id'],
            null,
            $attributes['golongan_id'],
            $attributes['mk_tahun'],
            $attributes['mk_bulan'],
        );

        return GajiBerkala::withTrashed()->firstOrCreate(['integrity_key' => $key], $attributes);
    }

    /** @param array<string, User> $accounts */
    private function seedCorrespondence(array $accounts): void
    {
        $umum = RefKlasifikasiSurat::where('kode', '421.1')->firstOrFail();
        $pegawai = RefKlasifikasiSurat::where('kode', '800')->firstOrFail();
        $siswa = RefKlasifikasiSurat::where('kode', '421.2')->firstOrFail();

        $incoming = SuratMasuk::withTrashed()->firstOrCreate(
            ['nomor_surat' => '005/1432/Disdik/X/2026'],
            [
                'tanggal_surat' => '2026-10-22',
                'tanggal_diterima' => '2026-10-23',
                'pengirim' => 'Dinas Pendidikan Provinsi Jawa Barat',
                'klasifikasi_id' => $umum->id,
                'lokasi_arsip_fisik' => 'Lemari A / Rak 2 / Ordner Dinas 2026',
                'tautan_gform' => 'https://drive.google.com/drive/folders/contoh-surat-masuk',
                'created_by' => $accounts['petugas_utama']->id,
            ],
        );
        Disposisi::firstOrCreate(
            ['surat_masuk_id' => $incoming->id, 'kepada' => 'Wakasek Kurikulum'],
            [
                'dari_user_id' => $accounts['petugas_kedua']->id,
                'instruksi' => 'Tindak lanjuti dan laporkan hasil koordinasi.',
                'prioritas' => 'penting',
                'batas_waktu' => '2026-10-30',
                'status' => 'diproses',
            ],
        );

        SuratMasuk::withTrashed()->firstOrCreate(
            ['nomor_surat' => '420/221/Kec.Sukajadi/2026'],
            [
                'tanggal_surat' => '2026-10-24',
                'tanggal_diterima' => '2026-10-25',
                'pengirim' => 'Kantor Kecamatan Sukajadi',
                'klasifikasi_id' => $siswa->id,
                'lokasi_arsip_fisik' => 'Map Surat Masuk Baru / Meja TU',
                'created_by' => $accounts['petugas_kedua']->id,
            ],
        );

        SuratKeluar::withTrashed()->firstOrCreate(
            ['nomor_surat' => '800/001/SMAN1/2026'],
            [
                'tanggal_surat' => '2026-10-24',
                'tujuan' => 'Dinas Pendidikan Provinsi Jawa Barat',
                'klasifikasi_id' => $pegawai->id,
                'lokasi_arsip_fisik' => 'Boks Surat Keluar 2026 / Rak A',
                'tautan_gform' => 'https://docs.google.com/spreadsheets/d/contoh-surat-keluar',
                'status' => 'terkirim',
                'created_by' => $accounts['petugas_utama']->id,
            ],
        );
        SuratKeluar::withTrashed()->firstOrCreate(
            ['nomor_surat' => '421.2/002/SMAN1/2026'],
            [
                'tanggal_surat' => '2026-10-26',
                'tujuan' => 'Orang Tua / Wali Siswa Kelas XII',
                'klasifikasi_id' => $siswa->id,
                'status' => 'draf',
                'created_by' => $accounts['petugas_kedua']->id,
            ],
        );
    }

    /** @param array<string, User> $accounts */
    private function seedLegalisir(array $accounts): void
    {
        Legalisir::withTrashed()->firstOrCreate(
            ['nomor_legalisir' => 'LEG/2026/9001'],
            [
                'nomor_antrean' => 'L-20261025-901',
                'nama_pemohon' => 'Muhammad Rizki Pratama',
                'kontak' => '081234567890',
                'status_pemohon' => 'alumni',
                'jenis_dokumen' => 'Ijazah',
                'jumlah_lembar' => 5,
                'tahun_lulus' => 2023,
                'lokasi_arsip_fisik' => 'Loker 2 / Rak B / LEG-2026-001',
                'tanggal_permohonan' => '2026-10-25',
                'status' => 'siap_diambil',
                'petugas_id' => $accounts['petugas_kedua']->id,
                'dokumen_asli_dilihat' => true,
                'identitas_cocok' => true,
                'data_sekolah_cocok' => true,
                'diverifikasi_oleh' => $accounts['petugas_kedua']->id,
                'diverifikasi_pada' => '2026-10-26 08:00:00',
                'tanggal_selesai' => '2026-10-26',
                'jadwal_pengambilan' => '2026-10-27 09:00:00',
                'tautan_lampiran' => 'https://drive.google.com/drive/folders/contoh-legalisir',
            ],
        );
        Legalisir::withTrashed()->firstOrCreate(
            ['nomor_legalisir' => 'LEG/2026/9002'],
            [
                'nomor_antrean' => 'L-20261026-902',
                'nama_pemohon' => 'Anisa Maharani',
                'kontak' => '085799881122',
                'status_pemohon' => 'alumni',
                'jenis_dokumen' => 'Transkrip Nilai',
                'jumlah_lembar' => 3,
                'tahun_lulus' => 2024,
                'lokasi_arsip_fisik' => 'Loker 2 / Rak B / LEG-2026-002',
                'tanggal_permohonan' => '2026-10-26',
                'status' => 'diterima',
                'petugas_id' => $accounts['petugas_utama']->id,
            ],
        );
    }
}
