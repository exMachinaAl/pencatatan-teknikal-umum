<?php

namespace Tests\Feature;

use App\Filament\Resources\KenaikanPangkatResource\Pages\ListKenaikanPangkats;
use App\Models\Disposisi;
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use App\Models\SuratMasuk;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::create([
            'name' => 'Kepala TU Proses',
            'username' => 'kepala.proses',
            'password' => 'password-yang-aman',
            'role' => User::ROLE_KEPALA_TU,
            'is_active' => true,
        ]));
    }

    public function test_kenaikan_pangkat_dilanjutkan_satu_tahap_dari_tabel(): void
    {
        $lama = RefGolongan::create(['kode' => 'III/a', 'pangkat' => 'Penata Muda', 'urutan' => 1]);
        $baru = RefGolongan::create(['kode' => 'III/b', 'pangkat' => 'Penata Muda Tk. I', 'urutan' => 2]);
        $pegawai = Pegawai::create(['nama' => 'Pegawai KP', 'golongan_id' => $lama->id]);
        $pengajuan = KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $lama->id,
            'golongan_baru_id' => $baru->id,
            'mk_tahun' => 8,
            'mk_bulan' => 0,
        ]);

        Livewire::test(ListKenaikanPangkats::class)
            ->callTableAction('lanjutkan_status', $pengajuan)
            ->assertHasNoTableActionErrors();

        $this->assertSame('diajukan', $pengajuan->refresh()->status);
    }

    public function test_kenaikan_pangkat_tidak_dapat_melompati_tahap(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Pegawai Lompatan KP']);
        $pengajuan = KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'mk_tahun' => 8,
            'mk_bulan' => 0,
        ]);

        $this->expectException(ValidationException::class);
        $pengajuan->update(['status' => 'sk_terbit']);
    }

    public function test_tmt_wajib_saat_sk_terbit_dan_golongan_berubah_otomatis_pada_tanggal_efektif(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');
        $lama = RefGolongan::create(['kode' => 'III/a', 'pangkat' => 'Penata Muda', 'urutan' => 1]);
        $baru = RefGolongan::create(['kode' => 'III/b', 'pangkat' => 'Penata Muda Tk. I', 'urutan' => 2]);
        $pegawai = Pegawai::create(['nama' => 'Pegawai TMT Otomatis', 'golongan_id' => $lama->id]);
        $pengajuan = KenaikanPangkat::create([
            'pegawai_id' => $pegawai->id,
            'jenis' => 'reguler',
            'golongan_lama_id' => $lama->id,
            'golongan_baru_id' => $baru->id,
            'mk_tahun' => 8,
            'mk_bulan' => 0,
            'status' => 'diajukan',
        ]);

        Livewire::test(ListKenaikanPangkats::class)
            ->callTableAction('lanjutkan_status', $pengajuan)
            ->assertHasTableActionErrors(['tmt' => 'required'])
            ->callTableAction('lanjutkan_status', $pengajuan, ['tmt' => '2026-11-01'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('sk_terbit', $pengajuan->refresh()->status);
        $this->assertSame($lama->id, $pegawai->fresh()->golongan_id);

        Carbon::setTestNow('2026-11-01 00:20:00');
        Artisan::call('app:sync-effective-promotions');

        $this->assertSame($baru->id, $pegawai->fresh()->golongan_id);
        $this->assertSame('2026-11-01', $pegawai->fresh()->tmt_pangkat_terakhir->toDateString());

        Artisan::call('app:sync-effective-promotions');
        $this->assertStringContainsString('0 golongan pegawai diperbarui', Artisan::output());
    }

    public function test_legalisir_tidak_dapat_melompati_tahap_verifikasi(): void
    {
        $legalisir = Legalisir::create([
            'nama_pemohon' => 'Pemohon Urutan',
            'jenis_dokumen' => 'Ijazah',
            'tanggal_permohonan' => today(),
            'dokumen_asli_dilihat' => true,
            'identitas_cocok' => true,
            'data_sekolah_cocok' => true,
        ]);

        $this->expectException(ValidationException::class);
        $legalisir->update(['status' => 'siap_diambil']);
    }

    public function test_surat_masuk_tidak_dapat_diarsipkan_selama_disposisi_berjalan(): void
    {
        $surat = SuratMasuk::create([
            'tanggal_diterima' => today(),
            'pengirim' => 'Dinas Penguji',
        ]);
        Disposisi::create([
            'surat_masuk_id' => $surat->id,
            'kepada' => 'Wakasek',
            'status' => 'terbuka',
        ]);

        $this->assertSame('didisposisi', $surat->refresh()->status);
        $this->expectException(ValidationException::class);
        $surat->update(['status' => 'diarsipkan']);
    }
}
