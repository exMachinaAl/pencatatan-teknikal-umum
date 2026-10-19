<?php

namespace Tests\Feature;

use App\Filament\Resources\SuratKeluarResource;
use App\Filament\Resources\SuratMasukResource;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SuratTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_agenda_surat_masuk_dihasilkan_otomatis_dan_urut(): void
    {
        $tahun = (int) date('Y');

        $agenda1 = SuratMasuk::generateNomorAgenda($tahun);
        $this->assertEquals("SM/{$tahun}/0001", $agenda1);

        SuratMasuk::create([
            'nomor_agenda' => $agenda1,
            'tanggal_diterima' => now(),
            'pengirim' => 'Dinas Pendidikan',
            'perihal' => 'Surat Edaran Awal',
        ]);

        $agenda2 = SuratMasuk::generateNomorAgenda($tahun);
        $this->assertEquals("SM/{$tahun}/0002", $agenda2);
    }

    public function test_nomor_surat_keluar_diisi_manual_unik_dan_tahun_mengikuti_tanggal(): void
    {
        $surat = SuratKeluar::create([
            'nomor_surat' => '421.1/001/SEKOLAH/2027',
            'tanggal_surat' => '2027-02-01',
            'tujuan' => 'Kantor Dinas',
            'perihal' => 'Laporan Bulanan',
        ]);

        $this->assertSame('421.1/001/SEKOLAH/2027', $surat->nomor_surat);
        $this->assertSame(2027, $surat->tahun);
        $this->assertNull($surat->nomor_urut);

        $this->assertValidationFails(fn () => SuratKeluar::create([
            'nomor_surat' => '421.1/001/SEKOLAH/2027',
            'tanggal_surat' => '2027-02-02',
            'tujuan' => 'Instansi Lain',
            'perihal' => 'Nomor ganda',
        ]), 'nomor_surat');

        $this->assertValidationFails(fn () => SuratKeluar::create([
            'tanggal_surat' => '2027-02-03',
            'tujuan' => 'Instansi Tanpa Nomor',
            'perihal' => 'Nomor wajib',
        ]), 'nomor_surat');
    }

    public function test_halaman_detail_surat_dapat_dibuka_tanpa_modal(): void
    {
        $user = User::create([
            'name' => 'Operator TU',
            'email' => 'operator-detail@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS_TU,
            'is_active' => true,
        ]);

        $suratMasuk = SuratMasuk::create([
            'nomor_agenda' => 'SM/2026/0099',
            'tanggal_diterima' => now(),
            'pengirim' => 'Dinas Pendidikan',
            'perihal' => 'Detail surat masuk',
        ]);

        $suratKeluar = SuratKeluar::create([
            'nomor_surat' => '421/099/SMAN1/2026',
            'nomor_urut' => 99,
            'tahun' => 2026,
            'tanggal_surat' => now(),
            'tujuan' => 'Kantor Dinas',
            'perihal' => 'Detail surat keluar',
        ]);

        $this->actingAs($user)
            ->get(SuratMasukResource::getUrl('view', ['record' => $suratMasuk]))
            ->assertOk()
            ->assertSee('Detail surat masuk');

        $this->get(SuratKeluarResource::getUrl('view', ['record' => $suratKeluar]))
            ->assertOk()
            ->assertSee('Detail surat keluar');
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
