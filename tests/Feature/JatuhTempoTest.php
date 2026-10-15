<?php

namespace Tests\Feature;

use App\Models\GajiBerkala;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JatuhTempoTest extends TestCase
{
    use RefreshDatabase;

    public function test_perhitungan_calon_kenaikan_pangkat_4_tahun(): void
    {
        // Pegawai yang TMT-nya 4 tahun lalu harus terdeteksi sebagai calon KP
        $pegawaiCalon = Pegawai::create([
            'nama' => 'Pak Guru Hebat',
            'status_kepegawaian' => 'PNS',
            'tmt_pangkat_terakhir' => Carbon::now()->subYears(4)->format('Y-m-d'),
        ]);

        $this->assertNotNull($pegawaiCalon->perkiraan_kp_berikutnya);
        $this->assertTrue($pegawaiCalon->is_calon_kp);

        // Pegawai yang baru 1 tahun belum calon KP
        $pegawaiBaru = Pegawai::create([
            'nama' => 'Ibu Guru Baru',
            'status_kepegawaian' => 'PNS',
            'tmt_pangkat_terakhir' => Carbon::now()->subYear()->format('Y-m-d'),
        ]);

        $this->assertFalse($pegawaiBaru->is_calon_kp);
    }

    public function test_perhitungan_jatuh_tempo_gaji_berkala_2_tahun(): void
    {
        $pegawai = Pegawai::create([
            'nama' => 'Staf Tata Usaha',
            'status_kepegawaian' => 'PNS',
            'tmt_kgb_terakhir' => Carbon::now()->subYears(2)->format('Y-m-d'),
        ]);

        $this->assertNotNull($pegawai->perkiraan_kgb_berikutnya);
        $this->assertTrue($pegawai->is_jatuh_tempo_kgb);

        // Uji otomatis pengisian tmt_berikutnya di model GajiBerkala
        $kgb = GajiBerkala::create([
            'pegawai_id' => $pegawai->id,
            'tmt' => '2024-01-01',
            'gaji_pokok_baru' => 3500000,
        ]);

        $this->assertEquals('2026-01-01', $kgb->tmt_berikutnya->format('Y-m-d'));
    }
}
