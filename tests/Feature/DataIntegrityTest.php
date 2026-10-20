<?php

namespace Tests\Feature;

use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_dibuat_saat_simpan_dan_nomor_soft_deleted_tidak_digunakan_ulang(): void
    {
        $first = SuratMasuk::create([
            'tanggal_diterima' => '2026-01-10',
            'pengirim' => 'Dinas A',
            'perihal' => 'Surat pertama',
        ]);
        $this->assertSame('SM/2026/0001', $first->nomor_agenda);

        $first->delete();
        $second = SuratMasuk::create([
            'tanggal_diterima' => '2026-01-11',
            'pengirim' => 'Dinas B',
            'perihal' => 'Surat kedua',
        ]);
        $this->assertSame('SM/2026/0002', $second->nomor_agenda);
        $second->nomor_agenda = 'SM/2026/9999';
        $this->assertValidationFails(fn () => $second->save(), 'nomor_agenda');
        $second->refresh();

        $outgoing = SuratKeluar::create([
            'nomor_surat' => '421.1/001/SEKOLAH/2027',
            'tanggal_surat' => '2027-02-01',
            'tujuan' => 'Dinas Pendidikan',
            'perihal' => 'Nomor konsisten',
        ]);
        $this->assertNull($outgoing->nomor_urut);
        $this->assertSame(2027, $outgoing->tahun);
        $this->assertSame('421.1/001/SEKOLAH/2027', $outgoing->nomor_surat);

        $outgoing->delete();
        $this->assertValidationFails(fn () => SuratKeluar::create([
            'nomor_surat' => '421.1/001/SEKOLAH/2027',
            'tanggal_surat' => '2027-02-02',
            'tujuan' => 'Dinas Lain',
            'perihal' => 'Nomor tidak boleh dipakai ulang',
        ]), 'nomor_surat');

        $legalisir = Legalisir::create([
            'nama_pemohon' => 'Pemohon',
            'jenis_dokumen' => 'Ijazah',
            'tanggal_permohonan' => '2028-03-02',
        ]);
        $this->assertSame('LEG/2028/0001', $legalisir->nomor_legalisir);
        $this->assertSame('L-20280302-001', $legalisir->nomor_antrean);
    }

    public function test_kp_final_menyinkronkan_master_tanpa_menimpa_riwayat_yang_lebih_baru(): void
    {
        [$old, $new] = $this->golonganPair();
        $pegawai = Pegawai::create([
            'nama' => 'Pegawai KP',
            'golongan_id' => $old->id,
            'tmt_pangkat_terakhir' => '2023-01-01',
        ]);

        KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $old->id,
            'golongan_baru_id' => $new->id,
            'tmt' => '2026-04-01',
            'status' => 'sk_terbit',
        ]);

        $this->assertSame($new->id, $pegawai->fresh()->golongan_id);
        $this->assertSame('2026-04-01', $pegawai->fresh()->tmt_pangkat_terakhir->toDateString());

        KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'pilihan',
            'golongan_baru_id' => $old->id,
            'tmt' => '2025-04-01',
            'status' => 'diserahkan',
        ]);

        $this->assertSame($new->id, $pegawai->fresh()->golongan_id);
        $this->assertSame('2026-04-01', $pegawai->fresh()->tmt_pangkat_terakhir->toDateString());
    }

    public function test_duplikat_kp_dan_kgb_tetap_ditolak_setelah_record_asli_soft_deleted(): void
    {
        [$old, $new] = $this->golonganPair();
        $pegawai = Pegawai::create(['nama' => 'Pegawai Duplikat']);

        $kp = KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $old->id,
            'golongan_baru_id' => $new->id,
            'tmt' => '2026-04-01',
        ]);
        $kp->delete();

        $this->assertValidationFails(fn () => KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $old->id,
            'golongan_baru_id' => $new->id,
            'tmt' => '2026-04-01',
        ]), 'tmt');

        $kgb = GajiBerkala::create([
            'pegawai_id' => $pegawai->id,
            'tmt' => '2026-06-01',
        ]);
        $kgb->delete();

        $this->assertValidationFails(fn () => GajiBerkala::create([
            'pegawai_id' => $pegawai->id,
            'tmt' => '2026-06-01',
        ]), 'tmt');
    }

    public function test_kp_tanpa_tmt_memakai_masa_kerja_dan_menolak_duplikat(): void
    {
        [$old, $new] = $this->golonganPair();
        $pegawai = Pegawai::create(['nama' => 'Pegawai KP Masa Kerja']);

        $kp = KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $old->id,
            'golongan_baru_id' => $new->id,
            'mk_tahun' => 8,
            'mk_bulan' => 6,
        ]);

        $this->assertNull($kp->tmt);
        $this->assertSame(8, $kp->mk_tahun);
        $this->assertSame(6, $kp->mk_bulan);
        $this->assertNotNull($kp->integrity_key);

        $this->assertValidationFails(fn () => KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $old->id,
            'golongan_baru_id' => $new->id,
            'mk_tahun' => 8,
            'mk_bulan' => 6,
        ]), 'mk_tahun');

        KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $old->id,
            'golongan_baru_id' => $new->id,
            'mk_tahun' => 8,
            'mk_bulan' => 7,
        ]);

        $this->assertValidationFails(fn () => KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'pilihan',
            'mk_tahun' => 9,
            'mk_bulan' => 12,
        ]), 'mk_bulan');

        $this->assertValidationFails(fn () => KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'pilihan',
            'mk_tahun' => 'delapan',
            'mk_bulan' => 0,
        ]), 'mk_tahun');

        $this->assertValidationFails(fn () => KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'penyesuaian_ijazah',
            'mk_tahun' => 10,
            'mk_bulan' => 0,
        ]), 'jenis');
    }

    public function test_kgb_memvalidasi_nominal_masa_kerja_dan_menyinkronkan_tmt_final(): void
    {
        $pegawai = Pegawai::create([
            'nama' => 'Pegawai KGB',
            'tmt_kgb_terakhir' => '2023-01-01',
        ]);

        GajiBerkala::create([
            'pegawai_id' => $pegawai->id,
            'tmt' => '2026-01-01',
            'gaji_pokok_lama' => 3_000_000,
            'gaji_pokok_baru' => 3_200_000,
            'mk_tahun' => 8,
            'mk_bulan' => 6,
            'status' => 'diserahkan',
        ]);

        $this->assertSame('2026-01-01', $pegawai->fresh()->tmt_kgb_terakhir->toDateString());

        GajiBerkala::create([
            'pegawai_id' => $pegawai->id,
            'tmt' => '2025-01-01',
            'status' => 'diserahkan',
        ]);
        $this->assertSame('2026-01-01', $pegawai->fresh()->tmt_kgb_terakhir->toDateString());

        $this->assertValidationFails(fn () => GajiBerkala::create([
            'pegawai_id' => $pegawai->id,
            'tmt' => '2027-01-01',
            'gaji_pokok_lama' => 3_500_000,
            'gaji_pokok_baru' => 3_400_000,
        ]), 'gaji_pokok_baru');

        $this->assertValidationFails(fn () => GajiBerkala::create([
            'pegawai_id' => $pegawai->id,
            'tmt' => '2027-02-01',
            'mk_bulan' => 12,
        ]), 'mk_bulan');

        $this->assertValidationFails(fn () => GajiBerkala::create([
            'pegawai_id' => $pegawai->id,
            'tmt' => '2027-03-01',
            'mk_tahun' => 'delapan',
        ]), 'mk_tahun');
    }

    public function test_tanggal_dan_nominal_ditolak_jika_melanggar_urutan_bisnis(): void
    {
        $this->assertValidationFails(fn () => SuratMasuk::create([
            'tanggal_surat' => '2026-05-02',
            'tanggal_diterima' => '2026-05-01',
            'pengirim' => 'Dinas Pendidikan',
            'perihal' => 'Tanggal tidak valid',
        ]), 'tanggal_surat');

        $this->assertValidationFails(fn () => Legalisir::create([
            'nama_pemohon' => 'Pemohon Invalid',
            'jenis_dokumen' => 'Ijazah',
            'tanggal_permohonan' => '2026-05-02',
            'tanggal_selesai' => '2026-05-01',
        ]), 'tanggal_selesai');

        $this->assertValidationFails(fn () => Legalisir::create([
            'nama_pemohon' => 'Pemohon Biaya',
            'jenis_dokumen' => 'Ijazah',
            'tanggal_permohonan' => '2026-05-02',
            'biaya' => -1,
        ]), 'biaya');

        $this->assertValidationFails(fn () => Legalisir::create([
            'nama_pemohon' => 'Pemohon Biaya Teks',
            'jenis_dokumen' => 'Ijazah',
            'tanggal_permohonan' => '2026-05-02',
            'biaya' => 'gratis',
        ]), 'biaya');
    }

    public function test_nip_tidak_dapat_dipakai_ulang_dari_record_soft_deleted(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '198001012006041001',
            'nama' => 'Pegawai Pertama',
            'status_kepegawaian' => 'PNS',
        ]);
        $pegawai->delete();

        $this->assertValidationFails(fn () => Pegawai::create([
            'nip' => '198001012006041001',
            'nama' => 'Pegawai Kedua',
            'status_kepegawaian' => 'PNS',
        ]), 'nip');
    }

    public function test_biodata_pegawai_memvalidasi_email_telepon_dan_tanggal_lahir(): void
    {
        $pegawai = Pegawai::create([
            'nama' => 'Pegawai Biodata',
            'email' => 'PEGAWAI@EXAMPLE.TEST',
            'nomor_telepon' => '+62 812-3456-7890',
            'jenis_kelamin' => 'P',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja' => 'Tata Usaha',
        ]);

        $this->assertSame('pegawai@example.test', $pegawai->email);
        $this->assertSame('Tata Usaha', $pegawai->unit_kerja);

        $this->assertValidationFails(fn () => Pegawai::create([
            'nama' => 'Email Ganda',
            'email' => 'pegawai@example.test',
        ]), 'email');

        $this->assertValidationFails(fn () => Pegawai::create([
            'nama' => 'Tanggal Masa Depan',
            'tanggal_lahir' => now()->addDay()->toDateString(),
        ]), 'tanggal_lahir');
    }

    public function test_database_seeder_data_contoh_aman_dijalankan_ulang(): void
    {
        $this->seed(DatabaseSeeder::class);

        $counts = [
            Pegawai::count(),
            KenaikanPangkat::count(),
            GajiBerkala::count(),
            SuratMasuk::count(),
            SuratKeluar::count(),
            Legalisir::count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [
            Pegawai::count(),
            KenaikanPangkat::count(),
            GajiBerkala::count(),
            SuratMasuk::count(),
            SuratKeluar::count(),
            Legalisir::count(),
        ]);
        $this->assertSame('197508152005011002', Pegawai::where('nama', 'Ahmad Fauzi, S.Pd., M.Si')->value('nip'));
    }

    private function golonganPair(): array
    {
        return [
            RefGolongan::create(['kode' => 'III/a', 'pangkat' => 'Penata Muda', 'urutan' => 1]),
            RefGolongan::create(['kode' => 'III/b', 'pangkat' => 'Penata Muda Tk. I', 'urutan' => 2]),
        ];
    }

    private function assertValidationFails(callable $callback, string $field): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            return;
        }

        $this->fail("Validasi untuk {$field} seharusnya gagal.");
    }
}
