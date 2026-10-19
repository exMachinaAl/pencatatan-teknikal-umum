<?php

namespace Tests\Feature;

use App\Filament\Pages\GlobalDocumentSearch;
use App\Models\AuditLog;
use App\Models\Disposisi;
use App\Models\Lampiran;
use App\Models\LampiranAccessLog;
use App\Models\Legalisir;
use App\Models\NotificationOutbox;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_legalisir_mendapat_antrean_token_dan_halaman_publik_tidak_mengekspos_data_pribadi(): void
    {
        $legalisir = $this->legalisir();

        $this->assertMatchesRegularExpression('/^L-\d{8}-\d{3}$/', $legalisir->nomor_antrean);
        $this->assertNotNull($legalisir->tracking_token_hash);
        $this->assertNotNull($legalisir->trackingUrl());

        $this->get($legalisir->trackingUrl())
            ->assertOk()
            ->assertSee($legalisir->nomor_antrean)
            ->assertSee('Verifikasi tanpa data pribadi')
            ->assertDontSee($legalisir->nama_pemohon)
            ->assertDontSee($legalisir->kontak)
            ->assertDontSee($legalisir->nomor_dokumen);
    }

    public function test_status_siap_diambil_membuat_outbox_email_dan_whatsapp_satu_kali(): void
    {
        $legalisir = $this->legalisir([
            'email_pemohon' => 'pemohon@example.test',
            'kanal_notifikasi' => 'keduanya',
        ]);

        $legalisir->update(['status' => 'siap_diambil']);
        $this->assertDatabaseCount('notification_outbox', 2);
        $this->assertEqualsCanonicalizing(['email', 'whatsapp'], NotificationOutbox::pluck('kanal')->all());

        $legalisir->update(['status' => 'diproses']);
        $legalisir->update(['status' => 'siap_diambil']);
        $this->assertDatabaseCount('notification_outbox', 2);
    }

    public function test_status_siap_diambil_tidak_mengantrekan_tujuan_email_yang_mengandung_header_baru(): void
    {
        $legalisir = $this->legalisir([
            'email_pemohon' => "pemohon@example.test\r\nBcc:penyerang@example.test",
            'kanal_notifikasi' => 'email',
        ]);

        $legalisir->update(['status' => 'siap_diambil']);

        $this->assertDatabaseCount('notification_outbox', 0);
    }

    public function test_proses_outbox_menolak_tujuan_email_tidak_valid_sebelum_mengirim(): void
    {
        Mail::fake();

        $outbox = NotificationOutbox::create([
            'entitas' => 'Legalisir',
            'entitas_id' => 999,
            'kanal' => 'email',
            'tujuan' => "pemohon@example.test\r\nBcc:penyerang@example.test",
            'template' => 'legalisir_siap_diambil',
            'payload' => [
                'nomor_antrean' => 'L-20261019-999',
                'tracking_url' => 'https://example.test/lacak',
            ],
            'status' => 'antre',
        ]);

        $this->artisan('notifications:process')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_outbox', [
            'id' => $outbox->id,
            'status' => 'gagal',
            'percobaan' => 1,
            'error_terakhir' => 'Tujuan notifikasi tidak valid.',
        ]);
    }

    public function test_tenggat_disposisi_relasi_balasan_dan_timeline_tercatat(): void
    {
        $this->actingAs($this->user());
        $masuk = SuratMasuk::create([
            'nomor_agenda' => 'SM/2026/0901', 'nomor_surat' => 'DINAS/44',
            'tanggal_diterima' => now(), 'pengirim' => 'Dinas Pendidikan', 'perihal' => 'Permintaan laporan',
        ]);
        $keluar = SuratKeluar::create([
            'nomor_surat' => '421/901/SMAN1/2026', 'nomor_urut' => 901, 'tahun' => 2026,
            'tanggal_surat' => now(), 'tujuan' => 'Dinas Pendidikan', 'perihal' => 'Jawaban laporan',
        ]);

        $disposisi = Disposisi::create([
            'surat_masuk_id' => $masuk->id, 'kepada' => 'Wakasek',
            'penanggung_jawab_id' => auth()->id(), 'batas_waktu' => today()->subDay(), 'status' => 'terbuka',
        ]);
        $keluar->suratMasukTerkait()->attach($masuk->id, ['created_by' => auth()->id()]);

        $this->assertTrue($disposisi->fresh()->is_terlambat);
        $this->assertTrue(Disposisi::terlambat()->whereKey($disposisi)->exists());
        $this->assertTrue($keluar->suratMasukTerkait()->whereKey($masuk)->exists());
        $this->assertTrue(AuditLog::where('entitas', 'SuratMasuk')->where('entitas_id', $masuk->id)->where('aksi', 'disposisi_ditambahkan')->exists());
    }

    public function test_lampiran_privat_memiliki_versi_dan_log_akses(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $this->actingAs($user);
        Storage::disk('local')->put('arsip_surat_masuk/v1.pdf', 'versi satu');
        Storage::disk('local')->put('arsip_surat_masuk/v2.pdf', 'versi dua');

        $surat = SuratMasuk::create([
            'nomor_agenda' => 'SM/2026/0902', 'tanggal_diterima' => now(),
            'pengirim' => 'Kecamatan', 'perihal' => 'Undangan rapat', 'file_path' => 'arsip_surat_masuk/v1.pdf',
        ]);
        $surat->update(['file_path' => 'arsip_surat_masuk/v2.pdf']);

        $versions = Lampiran::where('entitas', 'SuratMasuk')->where('entitas_id', $surat->id)->orderBy('versi')->get();
        $this->assertSame([1, 2], $versions->pluck('versi')->all());
        $this->assertFalse($versions[0]->is_current);
        $this->assertTrue($versions[1]->is_current);

        $this->get(route('lampiran.download', $versions[1]))->assertOk();
        $this->assertTrue(LampiranAccessLog::where('lampiran_id', $versions[1]->id)->where('user_id', $user->id)->exists());

        $this->actingAs($this->user(User::ROLE_PEGAWAI));
        $this->get(route('lampiran.download', $versions[1]))->assertForbidden();
    }

    public function test_preview_lampiran_dan_foto_profil_tetap_memeriksa_izin(): void
    {
        Storage::fake('local');
        $kepalaTu = $this->user(User::ROLE_KEPALA_TU);
        $this->actingAs($kepalaTu);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('local')->put('arsip_surat_masuk/preview.png', $png);
        Storage::disk('local')->put('profil_pengguna/kepala.png', $png);
        Storage::disk('local')->put('profil_pegawai/anggota.png', $png);

        $kepalaTu->update(['foto_path' => 'profil_pengguna/kepala.png']);
        $pegawai = \App\Models\Pegawai::create([
            'nama' => 'Anggota dengan Foto',
            'foto_path' => 'profil_pegawai/anggota.png',
        ]);
        $surat = SuratMasuk::create([
            'tanggal_diterima' => now(),
            'pengirim' => 'Dinas Pendidikan',
            'perihal' => 'Lampiran gambar',
            'file_path' => 'arsip_surat_masuk/preview.png',
        ]);
        $lampiran = $surat->lampiran()->firstOrFail();

        $this->get(route('lampiran.preview', $lampiran))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $this->get(route('profil.pengguna.foto', $kepalaTu))->assertOk();
        $this->get(route('profil.pegawai.foto', $pegawai))->assertOk();
        $this->assertTrue(LampiranAccessLog::where('lampiran_id', $lampiran->id)->where('aksi', 'preview')->exists());

        $this->actingAs($this->user(User::ROLE_PEGAWAI));
        $this->get(route('lampiran.preview', $lampiran))->assertForbidden();
        $this->get(route('profil.pengguna.foto', $kepalaTu))->assertForbidden();
        $this->get(route('profil.pegawai.foto', $pegawai))->assertForbidden();
    }

    public function test_pencarian_terpadu_mencari_nomor_pengirim_tujuan_perihal_dan_pemohon(): void
    {
        $this->actingAs($this->user());
        SuratMasuk::create([
            'nomor_agenda' => 'SM/2026/0991', 'nomor_surat' => 'KHUSUS-ALFA',
            'tanggal_diterima' => now(), 'pengirim' => 'Instansi Anggrek', 'perihal' => 'Agenda kebersihan',
        ]);
        SuratKeluar::create([
            'nomor_surat' => 'SK-KHUSUS-BETA', 'nomor_urut' => 992, 'tahun' => 2026,
            'tanggal_surat' => now(), 'tujuan' => 'Kantor Melati', 'perihal' => 'Konfirmasi kegiatan',
        ]);
        $this->legalisir(['nama_pemohon' => 'Pemohon Cempaka']);

        Livewire::test(GlobalDocumentSearch::class)->set('query', 'Anggrek')->assertSee('Agenda kebersihan');
        Livewire::test(GlobalDocumentSearch::class)->set('query', 'KHUSUS-ALFA')->assertSee('KHUSUS-ALFA');
        Livewire::test(GlobalDocumentSearch::class)->set('query', 'SM/2026/0991')->assertSee('KHUSUS-ALFA');
        Livewire::test(GlobalDocumentSearch::class)->set('query', 'Melati')->assertSee('Konfirmasi kegiatan');
        Livewire::test(GlobalDocumentSearch::class)->set('query', 'Cempaka')->assertSee('Pemohon Cempaka');
    }

    private function user(string $role = User::ROLE_PETUGAS_TU): User
    {
        return User::create([
            'name' => 'Petugas '.$role.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function legalisir(array $overrides = []): Legalisir
    {
        return Legalisir::create(array_merge([
            'nomor_legalisir' => 'LEG/2026/'.random_int(1000, 9999),
            'nama_pemohon' => 'Nama Rahasia',
            'kontak' => '081234567890',
            'nomor_dokumen' => 'IJZ-RAHASIA-001',
            'jenis_dokumen' => 'Ijazah',
            'jumlah_lembar' => 2,
            'tanggal_permohonan' => today(),
            'status' => 'diterima',
        ], $overrides));
    }
}
