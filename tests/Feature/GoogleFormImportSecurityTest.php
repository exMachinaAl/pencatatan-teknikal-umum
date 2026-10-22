<?php

namespace Tests\Feature;

use App\Filament\Pages\GoogleFormImport;
use App\Models\SuratKeluar;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleFormImportSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');
    }

    public function test_role_tanpa_izin_tidak_dapat_memaksa_mount_importer(): void
    {
        $this->actingAs($this->user(User::ROLE_KEPEGAWAIAN));

        Livewire::test(GoogleFormImport::class)->assertForbidden();
    }

    public function test_csv_hanya_dibaca_dari_folder_pengguna_dan_tanggal_invalid_ditolak(): void
    {
        $user = $this->user(User::ROLE_PETUGAS_TU);
        $this->actingAs($user);

        $upload = UploadedFile::fake()->createWithContent('surat.csv', implode("\n", [
            'tanggal_diterima,pengirim,nomor_surat,kode_klasifikasi,lokasi_arsip_fisik,tautan_gform',
            '2026-10-19,Dinas Pendidikan,001/DP/X/2026,421.1,Lemari A,https://example.test/surat',
            'tanggal-salah,Dinas Kesehatan,002/DK/X/2026,421.2,Lemari B,',
        ]));

        $component = Livewire::test(GoogleFormImport::class)
            ->fillForm(['modul' => 'surat_masuk', 'file' => [$upload]])
            ->call('parseCsv');

        $component->assertSet('step', 2);
        $storedPath = $component->get('filePath');
        $this->assertIsString($storedPath);
        $this->assertStringStartsWith("temp_imports/{$user->id}/", $storedPath);

        $component
            ->call('processImport')
            ->assertSet('step', 3)
            ->assertSet('importResult.total', 2)
            ->assertSet('importResult.success', 1)
            ->assertSet('importResult.failed', 1);

        $this->assertDatabaseHas('surat_masuk', [
            'pengirim' => 'Dinas Pendidikan',
            'tanggal_diterima' => '2026-10-19 00:00:00',
            'perihal' => '',
            'sifat' => 'biasa',
            'status' => 'baru',
            'lokasi_arsip_fisik' => 'Lemari A',
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('ref_klasifikasi_surat', ['kode' => '421.1']);
        $this->assertDatabaseMissing('surat_masuk', ['pengirim' => 'Dinas Kesehatan']);
        $this->assertDatabaseHas('import_gform_log', [
            'modul' => 'surat_masuk',
            'baris_total' => 2,
            'baris_berhasil' => 1,
            'baris_gagal' => 1,
            'imported_by' => $user->id,
        ]);
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_impor_surat_keluar_memakai_nomor_resmi_dari_csv(): void
    {
        $user = $this->user(User::ROLE_PETUGAS_TU);
        $this->actingAs($user);

        $upload = UploadedFile::fake()->createWithContent('surat-keluar.csv', implode("\n", [
            'nomor_surat,tanggal_surat,tujuan,kode_klasifikasi,lokasi_arsip_fisik,tautan_gform',
            '421.1/044/SEKOLAH/2027,2027-02-12,Dinas Pendidikan,421.1,Lemari B,',
        ]));

        Livewire::test(GoogleFormImport::class)
            ->fillForm(['modul' => 'surat_keluar', 'file' => [$upload]])
            ->call('parseCsv')
            ->assertSet('step', 2)
            ->call('processImport')
            ->assertSet('step', 3)
            ->assertSet('importResult.success', 1);

        $surat = SuratKeluar::where('nomor_surat', '421.1/044/SEKOLAH/2027')->firstOrFail();

        $this->assertSame(2027, $surat->tahun);
        $this->assertNull($surat->nomor_urut);
        $this->assertSame('', $surat->perihal);
        $this->assertSame('biasa', $surat->sifat);
        $this->assertSame('draf', $surat->status);
        $this->assertNull($surat->penandatangan);
        $this->assertNull($surat->pembuat_konsep);
        $this->assertSame('421.1', $surat->klasifikasi?->kode);
    }

    public function test_impor_legalisir_menyimpan_lokasi_arsip_tanpa_email_dan_notifikasi(): void
    {
        $user = $this->user(User::ROLE_PETUGAS_TU);
        $this->actingAs($user);

        $upload = UploadedFile::fake()->createWithContent('legalisir.csv', implode("\n", [
            'tanggal_permohonan,nama_pemohon,kontak,status_pemohon,jenis_dokumen,jumlah_lembar,lokasi_arsip_fisik,catatan',
            '2026-10-22,Pemohon Legalisir,081200000001,alumni,Ijazah,2,Loker 9 / Rak A / LEG-9,Berkas lengkap',
        ]));

        Livewire::test(GoogleFormImport::class)
            ->fillForm(['modul' => 'legalisir', 'file' => [$upload]])
            ->call('parseCsv')
            ->assertSet('step', 2)
            ->call('processImport')
            ->assertSet('step', 3)
            ->assertSet('importResult.success', 1);

        $this->assertDatabaseHas('legalisir', [
            'nama_pemohon' => 'Pemohon Legalisir',
            'kontak' => '081200000001',
            'email_pemohon' => null,
            'kanal_notifikasi' => 'tidak_ada',
            'nomor_dokumen' => null,
            'lokasi_arsip_fisik' => 'Loker 9 / Rak A / LEG-9',
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => $role,
            'email' => $role.uniqid().'@test.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
