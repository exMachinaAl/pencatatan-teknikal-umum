<?php

namespace Tests\Feature;

use App\Filament\Resources\SuratKeluarResource;
use App\Filament\Resources\SuratMasukResource;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_nomor_surat_keluar_dihasilkan_secara_atomic_dan_urut(): void
    {
        $tahun = (int) date('Y');

        $nomor1 = SuratKeluar::generateNomorSurat($tahun);
        $this->assertEquals(1, $nomor1['nomor_urut']);
        $this->assertEquals($tahun, $nomor1['tahun']);
        $this->assertStringContainsString('001', $nomor1['nomor_surat']);

        SuratKeluar::create([
            'nomor_surat' => $nomor1['nomor_surat'],
            'nomor_urut' => $nomor1['nomor_urut'],
            'tahun' => $nomor1['tahun'],
            'tanggal_surat' => now(),
            'tujuan' => 'Kantor Dinas',
            'perihal' => 'Laporan Bulanan',
        ]);

        $nomor2 = SuratKeluar::generateNomorSurat($tahun);
        $this->assertEquals(2, $nomor2['nomor_urut']);
        $this->assertStringContainsString('002', $nomor2['nomor_surat']);
    }

    public function test_halaman_detail_surat_dapat_dibuka_tanpa_modal(): void
    {
        $user = User::create([
            'name' => 'Operator TU',
            'email' => 'operator-detail@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
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
}
