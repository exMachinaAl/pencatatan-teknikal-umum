<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupManager;
use App\Filament\Pages\GoogleFormImport;
use App\Filament\Pages\TuDataExchangePage;
use App\Filament\Pages\TuPersonnelExchangePage;
use App\Filament\Pages\TuSecurityOverview;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\GajiBerkalaResource;
use App\Filament\Resources\GajiBerkalaResource\Pages\CreateGajiBerkala;
use App\Filament\Resources\GajiBerkalaResource\Pages\ListGajiBerkalas;
use App\Filament\Resources\KenaikanPangkatResource;
use App\Filament\Resources\KenaikanPangkatResource\Pages\CreateKenaikanPangkat;
use App\Filament\Resources\KenaikanPangkatResource\Pages\ListKenaikanPangkats;
use App\Filament\Resources\LegalisirResource;
use App\Filament\Resources\LegalisirResource\Pages\CreateLegalisir;
use App\Filament\Resources\LegalisirResource\Pages\ListLegalisirs;
use App\Filament\Resources\PegawaiResource;
use App\Filament\Resources\PegawaiResource\Pages\CreatePegawai;
use App\Filament\Resources\PegawaiResource\Pages\ListPegawais;
use App\Filament\Resources\SuratKeluarResource;
use App\Filament\Resources\SuratKeluarResource\Pages\CreateSuratKeluar;
use App\Filament\Resources\SuratKeluarResource\Pages\ListSuratKeluars;
use App\Filament\Resources\SuratMasukResource;
use App\Filament\Resources\SuratMasukResource\Pages\CreateSuratMasuk;
use App\Filament\Resources\SuratMasukResource\Pages\ListSuratMasuks;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Widgets\WorkspaceOverviewWidget;
use App\Models\GajiBerkala;
use App\Models\Legalisir;
use App\Models\NotificationOutbox;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\TuDataExchange;
use App\Services\TuPersonnelExchange;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_matriks_role_memisahkan_modul_dan_tindakan(): void
    {
        $matrix = [
            User::ROLE_ADMIN => [
                'users.view' => true, 'backup.manage' => true,
                'surat.view' => true, 'surat.create' => false, 'surat.update' => false,
                'legalisir.view' => true, 'legalisir.transition' => false, 'kepegawaian.view' => false,
            ],
            User::ROLE_KEPALA_TU => [
                'users.view' => false, 'surat.transition' => true,
                'legalisir.transition' => true, 'kepegawaian.transition' => true,
            ],
            User::ROLE_PETUGAS_TU => [
                'users.view' => false, 'surat.update' => true,
                'legalisir.update' => true, 'kepegawaian.view' => false,
            ],
            User::ROLE_KEPEGAWAIAN => [
                'users.view' => false, 'surat.view' => false,
                'legalisir.view' => false, 'kepegawaian.update' => true,
            ],
            User::ROLE_KEPALA_SEKOLAH => [
                'surat.view' => true, 'surat.update' => false,
                'legalisir.view' => true, 'kepegawaian.view' => true,
            ],
            User::ROLE_AUDITOR => [
                'surat.view' => true, 'surat.export' => false,
                'audit.view' => true, 'kepegawaian.update' => false,
            ],
            User::ROLE_PEGAWAI => [
                'surat.view' => false, 'legalisir.view' => false,
                'kepegawaian.view' => false, 'users.view' => false,
            ],
        ];

        foreach ($matrix as $role => $permissions) {
            $user = $this->user($role, $role.'@test.com');
            foreach ($permissions as $permission => $expected) {
                [$module, $action] = explode('.', $permission);
                $this->assertSame($expected, $user->canPerform($module, $action), "{$role}: {$permission}");
            }
        }
    }

    public function test_administrator_sistem_dan_kepala_tu_memiliki_hierarki_yang_berbeda(): void
    {
        $this->assertSame('Administrator Sistem (Fasilitator TI)', User::ROLE_LABELS[User::ROLE_ADMIN]);
        $this->assertSame('Kepala Tata Usaha (Pimpinan TU)', User::ROLE_LABELS[User::ROLE_KEPALA_TU]);
        $this->assertTrue(User::roleCanPerform(User::ROLE_ADMIN, 'users', 'update'));
        $this->assertFalse(User::roleCanPerform(User::ROLE_ADMIN, 'surat', 'update'));
        $this->assertFalse(User::roleCanPerform(User::ROLE_KEPALA_TU, 'users', 'update'));
        $this->assertTrue(User::roleCanPerform(User::ROLE_KEPALA_TU, 'surat', 'update'));
        $this->assertTrue(User::roleCanPerform(User::ROLE_KEPALA_TU, 'kepegawaian', 'transition'));
        $this->assertTrue(User::roleCanPerform(User::ROLE_KEPALA_TU, 'audit', 'view'));
    }

    public function test_laravel_policy_menggunakan_matriks_role_yang_sama_dengan_resource(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'policy-admin@test.com');
        $kepalaTu = $this->user(User::ROLE_KEPALA_TU, 'policy-kepala-tu@test.com');
        $kepegawaian = $this->user(User::ROLE_KEPEGAWAIAN, 'policy-kepegawaian@test.com');
        $petugasTu = $this->user(User::ROLE_PETUGAS_TU, 'policy-petugas-tu@test.com');

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', SuratMasuk::class));
        $this->assertFalse(Gate::forUser($admin)->allows('create', SuratMasuk::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', NotificationOutbox::class));
        $this->assertFalse(Gate::forUser($admin)->allows('create', NotificationOutbox::class));

        $this->assertTrue(Gate::forUser($kepalaTu)->allows('create', SuratMasuk::class));
        $this->assertFalse(Gate::forUser($kepalaTu)->allows('deleteAny', SuratMasuk::class));
        $this->assertTrue(Gate::forUser($kepegawaian)->allows('viewAny', GajiBerkala::class));
        $this->assertTrue(Gate::forUser($kepegawaian)->allows('create', Pegawai::class));
        $this->assertFalse(Gate::forUser($petugasTu)->allows('viewAny', Pegawai::class));
    }

    public function test_halaman_exchange_dan_ringkasan_keamanan_mengikuti_role_baru(): void
    {
        $this->actingAs($this->user(User::ROLE_PETUGAS_TU, 'exchange-petugas@test.com'));
        $this->get(TuDataExchangePage::getUrl())->assertOk();
        $this->get(TuPersonnelExchangePage::getUrl())->assertForbidden();
        $this->get(TuSecurityOverview::getUrl())->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->user(User::ROLE_KEPEGAWAIAN, 'exchange-kepegawaian@test.com'));
        $this->get(TuDataExchangePage::getUrl())->assertForbidden();
        $this->get(TuPersonnelExchangePage::getUrl())->assertOk();
        $this->assertTrue(TuPersonnelExchange::authorized(User::ROLE_KEPEGAWAIAN, 'kgb', true));

        $this->flushSession();
        $this->actingAs($this->user(User::ROLE_ADMIN, 'exchange-admin@test.com'));
        $this->get(TuDataExchangePage::getUrl())->assertForbidden();
        $this->get(TuPersonnelExchangePage::getUrl())->assertForbidden();
        $this->get(TuSecurityOverview::getUrl())
            ->assertOk()
            ->assertSee('tu-access-page', false)
            ->assertSee('Akses setiap peran dalam satu tampilan')
            ->assertSee('Hak akses per modul')
            ->assertSee('Aktivitas perubahan terbaru')
            ->assertSee('Lihat semua audit')
            ->assertDontSee('Tombol aksi kustom pada Resource lama');

        $this->flushSession();
        $this->actingAs($this->user(User::ROLE_AUDITOR, 'exchange-auditor@test.com'));
        $this->get(TuSecurityOverview::getUrl())->assertOk();
    }

    public function test_endpoint_privat_mengarahkan_tamu_ke_login_filament(): void
    {
        $this->get(route('legalisir.cetak-bukti', 1))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_backup_manager_menolak_path_di_luar_folder_backup(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 'backup-admin@test.com'));

        Livewire::test(BackupManager::class)
            ->call('downloadBackup', '../.env')
            ->assertNotFound();
    }

    public function test_petugas_tu_tidak_dapat_membuka_pengguna_atau_kepegawaian_dari_url(): void
    {
        $this->actingAs($this->user(User::ROLE_PETUGAS_TU));

        $this->get(SuratMasukResource::getUrl())->assertOk();
        $this->get(LegalisirResource::getUrl())->assertOk();
        $this->get(GoogleFormImport::getUrl())->assertOk();
        $this->get(UserResource::getUrl())->assertForbidden();
        $this->get(PegawaiResource::getUrl())->assertForbidden();
        $this->get(GajiBerkalaResource::getUrl())->assertForbidden();
        $this->get(BackupManager::getUrl())->assertForbidden();
    }

    public function test_form_pegawai_ringkas_dan_form_pengguna_memuat_login_utama(): void
    {
        $pegawai = Pegawai::create([
            'nama' => 'Pegawai Uji Profil',
            'status_kepegawaian' => 'PNS',
        ]);

        $this->actingAs($this->user(User::ROLE_KEPEGAWAIAN, 'form-kepegawaian@test.com'));
        $golongan = RefGolongan::create([
            'kode' => 'III/a',
            'pangkat' => 'Penata Muda',
            'tingkat' => 'III',
            'urutan' => 1,
        ]);

        $this->get(PegawaiResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Nama Lengkap &amp; Gelar', false)
            ->assertSee('NIP')
            ->assertSee('Status Kepegawaian')
            ->assertSee('Golongan / Ruang')
            ->assertDontSee('Foto Profil Pegawai')
            ->assertDontSee('Kontak &amp; Biodata', false)
            ->assertDontSee('Riwayat TMT Terakhir');
        Livewire::test(CreatePegawai::class)
            ->fillForm([
                'nama' => 'Siti Aminah, S.Pd.',
                'nip' => '198501012010012001',
                'status_kepegawaian' => 'PNS',
                'golongan_id' => $golongan->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('pegawai', [
            'nama' => 'Siti Aminah, S.Pd.',
            'nip' => '198501012010012001',
            'status_kepegawaian' => 'PNS',
            'golongan_id' => $golongan->id,
        ]);
        $this->get(PegawaiResource::getUrl('edit', ['record' => $pegawai]))
            ->assertOk()
            ->assertSee('Data Inti Pegawai')
            ->assertDontSee('Preview tersimpan');

        $this->flushSession();
        $admin = $this->user(User::ROLE_ADMIN, 'form-admin@test.com');
        $this->actingAs($admin);

        $this->get(UserResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Username')
            ->assertSee('NIP pegawai (opsional)')
            ->assertSee('Email (opsional)')
            ->assertDontSee('Foto Pengurus / Pengguna')
            ->assertDontSee('Jabatan Organisasi');
        $this->get(UserResource::getUrl('edit', ['record' => $admin]))
            ->assertOk()
            ->assertSee('Username');
    }

    public function test_form_kenaikan_pangkat_ringkas_memakai_selector_masa_kerja(): void
    {
        $golonganLama = RefGolongan::create([
            'kode' => 'II/a',
            'pangkat' => 'Pengatur Muda',
            'tingkat' => 'II',
            'urutan' => 1,
        ]);
        $golonganBaru = RefGolongan::create([
            'kode' => 'II/b',
            'pangkat' => 'Pengatur Muda Tk. I',
            'tingkat' => 'II',
            'urutan' => 2,
        ]);
        $pegawai = Pegawai::create([
            'nama' => 'Pegawai Pengajuan KP',
            'status_kepegawaian' => 'PNS',
            'golongan_id' => $golonganLama->id,
        ]);

        $this->actingAs($this->user(User::ROLE_KEPEGAWAIAN, 'form-kp@test.com'));

        $this->get(KenaikanPangkatResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Pegawai yang Mengajukan')
            ->assertSee('Jenis Kenaikan Pangkat')
            ->assertSee('Golongan Lama')
            ->assertSee('Golongan Baru (Tujuan)')
            ->assertSee('Masa Kerja (Tahun)')
            ->assertSee('Masa Kerja (Bulan)')
            ->assertSee('Tautan Lampiran / Google Sheets (Opsional)')
            ->assertDontSee('Penyesuaian Ijazah')
            ->assertDontSee('Periode Pengajuan')
            ->assertDontSee('TMT Kenaikan Pangkat')
            ->assertDontSee('Nomor SK')
            ->assertDontSee('Tanggal SK')
            ->assertDontSee('Pejabat Penetap');

        Livewire::test(CreateKenaikanPangkat::class)
            ->fillForm([
                'pegawai_id' => $pegawai->id,
                'jenis' => 'reguler',
                'golongan_baru_id' => $golonganBaru->id,
                'mk_tahun' => 8,
                'mk_bulan' => 6,
                'tautan_lampiran' => 'https://drive.google.com/file/d/kp-contoh/view',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('kenaikan_pangkat', [
            'pegawai_id' => $pegawai->id,
            'golongan_lama_id' => $golonganLama->id,
            'golongan_baru_id' => $golonganBaru->id,
            'mk_tahun' => 8,
            'mk_bulan' => 6,
            'tautan_lampiran' => 'https://drive.google.com/file/d/kp-contoh/view',
            'tmt' => null,
            'periode' => null,
            'nomor_sk' => null,
            'status' => 'berkas_disiapkan',
        ]);

        $this->assertSame([
            'pegawai_nip',
            'pegawai_nama',
            'jenis',
            'golongan_lama_kode',
            'golongan_baru_kode',
            'mk_tahun',
            'mk_bulan',
            'tautan_lampiran',
        ], TuPersonnelExchange::template('kp')[0]);
    }

    public function test_tabel_kepegawaian_mengikuti_input_baru(): void
    {
        $this->actingAs($this->user(User::ROLE_KEPEGAWAIAN, 'tabel-pegawai@test.com'));

        Livewire::test(ListPegawais::class)
            ->assertTableColumnExists('mobile_summary')
            ->assertTableColumnExists('nama')
            ->assertTableColumnExists('status_kepegawaian')
            ->assertTableColumnExists('golongan.kode')
            ->assertTableColumnDoesNotExist('foto_path')
            ->assertTableColumnDoesNotExist('jabatan')
            ->assertTableColumnDoesNotExist('nomor_telepon')
            ->assertTableColumnDoesNotExist('tmt_pangkat_terakhir')
            ->assertTableColumnDoesNotExist('perkiraan_kp_berikutnya')
            ->assertTableColumnDoesNotExist('perkiraan_kgb_berikutnya')
            ->assertTableColumnDoesNotExist('is_active');

        Livewire::test(ListKenaikanPangkats::class)
            ->assertTableColumnExists('mobile_summary')
            ->assertTableColumnExists('pegawai.nama')
            ->assertTableColumnExists('jenis')
            ->assertTableColumnExists('golonganLama.kode')
            ->assertTableColumnExists('golonganBaru.kode')
            ->assertTableColumnExists('masa_kerja')
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('document_links')
            ->assertTableColumnDoesNotExist('periode')
            ->assertTableColumnDoesNotExist('tmt')
            ->assertTableColumnDoesNotExist('nomor_sk');
    }

    public function test_form_dan_tabel_gaji_berkala_hanya_memakai_data_baru(): void
    {
        $golongan = RefGolongan::create([
            'kode' => 'III/c',
            'pangkat' => 'Penata',
            'tingkat' => 'III',
            'urutan' => 3,
        ]);
        $pegawai = Pegawai::create([
            'nama' => 'Pegawai Pengajuan KGB',
            'status_kepegawaian' => 'PNS',
            'golongan_id' => $golongan->id,
        ]);

        $this->actingAs($this->user(User::ROLE_KEPEGAWAIAN, 'form-kgb@test.com'));

        $this->get(GajiBerkalaResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Nama Pegawai')
            ->assertSee('Golongan / Ruang')
            ->assertSee('Masa Kerja (Tahun)')
            ->assertSee('Masa Kerja (Bulan)')
            ->assertSee('Tautan Lampiran / Google Sheets (Opsional)')
            ->assertDontSee('TMT KGB')
            ->assertDontSee('Gaji Pokok Lama')
            ->assertDontSee('Gaji Pokok Baru')
            ->assertDontSee('Nomor SK')
            ->assertDontSee('Pejabat Penetap')
            ->assertDontSee('Catatan Tambahan');

        Livewire::test(CreateGajiBerkala::class)
            ->fillForm([
                'pegawai_id' => $pegawai->id,
                'golongan_id' => $golongan->id,
                'mk_tahun' => 9,
                'mk_bulan' => 4,
                'tautan_lampiran' => 'https://docs.google.com/spreadsheets/d/kgb-contoh/edit',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('gaji_berkala', [
            'pegawai_id' => $pegawai->id,
            'golongan_id' => $golongan->id,
            'mk_tahun' => 9,
            'mk_bulan' => 4,
            'tautan_lampiran' => 'https://docs.google.com/spreadsheets/d/kgb-contoh/edit',
            'tmt' => null,
            'gaji_pokok_lama' => null,
            'gaji_pokok_baru' => null,
            'nomor_sk' => null,
            'status' => 'diproses',
        ]);

        Livewire::test(ListGajiBerkalas::class)
            ->assertTableColumnExists('mobile_summary')
            ->assertTableColumnExists('pegawai.nama')
            ->assertTableColumnExists('golongan.kode')
            ->assertTableColumnExists('masa_kerja')
            ->assertTableColumnExists('document_links')
            ->assertTableColumnDoesNotExist('tmt')
            ->assertTableColumnDoesNotExist('tmt_berikutnya')
            ->assertTableColumnDoesNotExist('gaji_pokok_baru')
            ->assertTableColumnDoesNotExist('status')
            ->assertTableColumnDoesNotExist('nomor_sk')
            ->assertTableActionDoesNotExist('tandai_diserahkan');

        [$headers] = TuPersonnelExchange::template('kgb');
        $this->assertSame([
            'pegawai_nip',
            'pegawai_nama',
            'golongan_kode',
            'mk_tahun',
            'mk_bulan',
            'tautan_lampiran',
        ], $headers);

        $csvPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kgb-ringkas-'.uniqid().'.csv';
        file_put_contents($csvPath, implode(PHP_EOL, [
            'pegawai_nip,pegawai_nama,golongan_kode,mk_tahun,mk_bulan,tautan_lampiran',
            ',Pegawai Pengajuan KGB,III/c,10,1,https://drive.google.com/file/d/kgb-impor/view',
        ]));

        try {
            $result = TuPersonnelExchange::import('kgb', $csvPath, (int) auth()->id());
        } finally {
            @unlink($csvPath);
        }

        $this->assertSame(1, $result['success']);
        $this->assertDatabaseHas('gaji_berkala', [
            'pegawai_id' => $pegawai->id,
            'golongan_id' => $golongan->id,
            'mk_tahun' => 10,
            'mk_bulan' => 1,
            'tautan_lampiran' => 'https://drive.google.com/file/d/kgb-impor/view',
            'tmt' => null,
        ]);

        $export = TuPersonnelExchange::export('kgb', 'Pegawai Pengajuan KGB', null, null);
        $this->assertSame($headers, $export[0]);
        $this->assertCount(3, $export);
    }

    public function test_form_legalisir_ringkas_dan_lokasi_arsip_dapat_dicari(): void
    {
        $this->actingAs($this->user(User::ROLE_PETUGAS_TU, 'form-legalisir@test.com'));

        $this->get(LegalisirResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Lokasi Arsip Fisik / Kode Penyimpanan')
            ->assertDontSee('Biaya Administrasi')
            ->assertDontSee('Email Pemohon')
            ->assertDontSee('Notifikasi Saat Siap')
            ->assertDontSee('Nomor Dokumen');

        Livewire::test(CreateLegalisir::class)
            ->fillForm([
                'tanggal_permohonan' => now()->format('Y-m-d'),
                'nama_pemohon' => 'Pemohon Arsip Loker',
                'kontak' => '081234567890',
                'status_pemohon' => 'alumni',
                'jenis_dokumen' => 'Ijazah',
                'jumlah_lembar' => 2,
                'lokasi_arsip_fisik' => 'Loker 7 / Rak C / LEG-77',
                'status' => 'diterima',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('legalisir', [
            'nama_pemohon' => 'Pemohon Arsip Loker',
            'kontak' => '081234567890',
            'email_pemohon' => null,
            'kanal_notifikasi' => 'tidak_ada',
            'nomor_dokumen' => null,
            'lokasi_arsip_fisik' => 'Loker 7 / Rak C / LEG-77',
            'biaya' => 0,
        ]);
        $legalisir = Legalisir::where('nama_pemohon', 'Pemohon Arsip Loker')->firstOrFail();

        Livewire::test(ListLegalisirs::class)
            ->assertTableColumnExists('lokasi_arsip_fisik')
            ->searchTable('LEG-77')
            ->assertCanSeeTableRecords([$legalisir]);

        $this->assertContains('lokasi_arsip_fisik', TuDataExchange::template('legalisir')[0]);
        $this->assertSame(1, TuDataExchange::query('legalisir', 'LEG-77', null, null)->count());

        $export = TuDataExchange::exportRows('legalisir', 'LEG-77', null, null);
        $locationColumn = array_search('lokasi_arsip_fisik', $export[0], true);
        $this->assertNotFalse($locationColumn);
        $this->assertSame('Loker 7 / Rak C / LEG-77', $export[1][$locationColumn]);
    }

    public function test_form_tabel_dan_pertukaran_surat_masuk_memakai_input_ringkas(): void
    {
        $classification = RefKlasifikasiSurat::create([
            'kode' => '421.9',
            'nama' => 'Administrasi Sekolah',
        ]);
        $this->actingAs($this->user(User::ROLE_PETUGAS_TU, 'form-surat-masuk@test.com'));

        $this->get(SuratMasukResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Kode Klasifikasi Surat')
            ->assertSee('Softfile Scan Surat')
            ->assertDontSee('Perihal / Ringkasan Isi Surat')
            ->assertDontSee('Status Surat')
            ->assertDontSee('Sifat Surat');

        Livewire::test(CreateSuratMasuk::class)
            ->fillForm([
                'tanggal_diterima' => '2026-10-22',
                'nomor_surat' => 'IN/421.9/2026',
                'tanggal_surat' => '2026-10-21',
                'pengirim' => 'Dinas Pendidikan',
                'klasifikasi_id' => $classification->id,
                'lokasi_arsip_fisik' => 'Lemari A',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('surat_masuk', [
            'nomor_surat' => 'IN/421.9/2026',
            'pengirim' => 'Dinas Pendidikan',
            'perihal' => '',
            'sifat' => 'biasa',
            'status' => 'baru',
            'klasifikasi_id' => $classification->id,
        ]);
        $letter = SuratMasuk::where('nomor_surat', 'IN/421.9/2026')->firstOrFail();

        Livewire::test(ListSuratMasuks::class)
            ->assertTableColumnExists('pengirim')
            ->assertTableColumnExists('klasifikasi.kode')
            ->assertTableColumnExists('status')
            ->assertTableColumnDoesNotExist('perihal')
            ->assertTableColumnDoesNotExist('sifat');

        Livewire::test(ListSuratMasuks::class)
            ->searchTable('421.9')
            ->assertCanSeeTableRecords([$letter]);

        $this->assertSame([
            'nomor_agenda',
            'nomor_surat',
            'tanggal_surat',
            'tanggal_diterima',
            'pengirim',
            'kode_klasifikasi',
            'lokasi_arsip_fisik',
            'tautan_gform',
        ], TuDataExchange::template('surat_masuk')[0]);

        $this->assertSame(1, TuDataExchange::query('surat_masuk', '421.9', null, null)->count());
    }

    public function test_form_tabel_dan_pertukaran_surat_keluar_memakai_input_ringkas(): void
    {
        $classification = RefKlasifikasiSurat::create([
            'kode' => '422.8',
            'nama' => 'Administrasi Keluar',
        ]);
        $this->actingAs($this->user(User::ROLE_PETUGAS_TU, 'form-surat-keluar@test.com'));

        $this->get(SuratKeluarResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Klasifikasi &amp; Arsip Digital', false)
            ->assertSee('Kode Klasifikasi Surat')
            ->assertSee('Softfile Final')
            ->assertDontSee('Perihal Surat')
            ->assertDontSee('Sifat Surat')
            ->assertDontSee('Penandatangan')
            ->assertDontSee('Pembuat Konsep / Pengetik')
            ->assertDontSee('Status Surat');

        Livewire::test(CreateSuratKeluar::class)
            ->fillForm([
                'nomor_surat' => 'OUT/422.8/2026',
                'tanggal_surat' => '2026-10-22',
                'tujuan' => 'Dinas Pendidikan',
                'klasifikasi_id' => $classification->id,
                'lokasi_arsip_fisik' => 'Lemari B',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => 'OUT/422.8/2026',
            'tujuan' => 'Dinas Pendidikan',
            'perihal' => '',
            'sifat' => 'biasa',
            'status' => 'draf',
            'penandatangan' => null,
            'pembuat_konsep' => null,
            'klasifikasi_id' => $classification->id,
        ]);
        $letter = SuratKeluar::where('nomor_surat', 'OUT/422.8/2026')->firstOrFail();

        Livewire::test(ListSuratKeluars::class)
            ->assertTableColumnExists('tujuan')
            ->assertTableColumnExists('klasifikasi.kode')
            ->assertTableColumnExists('status')
            ->assertTableColumnDoesNotExist('perihal')
            ->assertTableColumnDoesNotExist('sifat')
            ->searchTable('422.8')
            ->assertCanSeeTableRecords([$letter]);

        $this->assertSame([
            'nomor_surat',
            'tanggal_surat',
            'tujuan',
            'kode_klasifikasi',
            'lokasi_arsip_fisik',
            'tautan_gform',
        ], TuDataExchange::template('surat_keluar')[0]);

        $this->assertSame(1, TuDataExchange::query('surat_keluar', '422.8', null, null)->count());

        $csvPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'surat-keluar-ringkas-'.uniqid().'.csv';
        file_put_contents($csvPath, implode(PHP_EOL, [
            'nomor_surat,tanggal_surat,tujuan,kode_klasifikasi,lokasi_arsip_fisik,tautan_gform',
            'OUT/423.1/2026,2026-10-22,Kantor Cabang,423.1,Lemari C,https://drive.google.com/file/d/surat-keluar/view',
        ]));

        try {
            $result = TuDataExchange::import('surat_keluar', $csvPath, (int) auth()->id());
        } finally {
            @unlink($csvPath);
        }

        $this->assertSame(1, $result['success']);
        $this->assertDatabaseHas('ref_klasifikasi_surat', ['kode' => '423.1']);
        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => 'OUT/423.1/2026',
            'tujuan' => 'Kantor Cabang',
            'perihal' => '',
            'status' => 'draf',
            'pembuat_konsep' => null,
        ]);

        $export = TuDataExchange::exportRows('surat_keluar', '423.1', null, null);
        $classificationColumn = array_search('kode_klasifikasi', $export[0], true);
        $this->assertNotFalse($classificationColumn);
        $this->assertSame('423.1', $export[1][$classificationColumn]);
    }

    public function test_administrator_sistem_hanya_dapat_membaca_persuratan_dan_legalisir(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN));

        $surat = $this->suratKeluar();
        $legalisir = Legalisir::create([
            'nomor_legalisir' => 'LEG/2026/0777', 'nama_pemohon' => 'Alumni',
            'status_pemohon' => 'alumni', 'jenis_dokumen' => 'Ijazah',
            'tanggal_permohonan' => now(), 'status' => 'diterima',
        ]);

        $this->get(UserResource::getUrl())->assertOk();
        $this->get(AuditLogResource::getUrl())->assertOk();
        $this->get(SuratMasukResource::getUrl())->assertOk();
        $this->get(SuratKeluarResource::getUrl())->assertOk();
        $this->get(LegalisirResource::getUrl())->assertOk();

        $this->get(SuratMasukResource::getUrl('create'))->assertForbidden();
        $this->get(SuratKeluarResource::getUrl('edit', ['record' => $surat]))->assertForbidden();
        $this->get(LegalisirResource::getUrl('edit', ['record' => $legalisir]))->assertForbidden();
        $this->get(GoogleFormImport::getUrl())->assertForbidden();
        $this->get(route('legalisir.cetak-bukti', $legalisir))->assertForbidden();

        $this->assertFalse(SuratKeluarResource::canEdit($surat));
        $this->assertFalse(LegalisirResource::canEdit($legalisir));
        $this->get(PegawaiResource::getUrl())->assertForbidden();
    }

    public function test_role_baca_saja_tidak_dapat_membuat_atau_mengedit_record(): void
    {
        $surat = $this->suratKeluar();
        $auditor = $this->user(User::ROLE_AUDITOR);
        $this->actingAs($auditor);

        $this->get(SuratKeluarResource::getUrl())->assertOk();
        $this->get(SuratKeluarResource::getUrl('create'))->assertForbidden();
        $this->get(SuratKeluarResource::getUrl('edit', ['record' => $surat]))->assertForbidden();
        $this->assertFalse(SuratKeluarResource::canCreate());
        $this->assertFalse(SuratKeluarResource::canEdit($surat));
    }

    public function test_arsip_dan_record_final_tidak_dapat_diedit_langsung(): void
    {
        $this->actingAs($this->user(User::ROLE_KEPALA_TU));

        $suratMasuk = SuratMasuk::create([
            'nomor_agenda' => 'SM/2026/0888', 'tanggal_diterima' => now(),
            'pengirim' => 'Dinas', 'perihal' => 'Arsip', 'status' => 'diarsipkan',
        ]);
        $legalisir = Legalisir::create([
            'nomor_legalisir' => 'LEG/2026/0888', 'nama_pemohon' => 'Alumni',
            'status_pemohon' => 'alumni', 'jenis_dokumen' => 'Ijazah',
            'tanggal_permohonan' => now(), 'status' => 'diambil',
            'dokumen_asli_dilihat' => true, 'identitas_cocok' => true,
            'data_sekolah_cocok' => true, 'tanggal_diambil' => now(),
            'pengambil' => 'Alumni',
        ]);

        $this->assertFalse(SuratMasukResource::canEdit($suratMasuk));
        $this->assertFalse(LegalisirResource::canEdit($legalisir));
        $this->assertFalse(Gate::forUser(auth()->user())->allows('update', $suratMasuk));
        $this->assertFalse(Gate::forUser(auth()->user())->allows('update', $legalisir));
    }

    public function test_aksi_titik_tiga_memperbarui_status_tabel_tanpa_reload_manual(): void
    {
        $this->actingAs($this->user(User::ROLE_PETUGAS_TU));
        $surat = $this->suratKeluar();

        Livewire::test(ListSuratKeluars::class)
            ->assertCanSeeTableRecords([$surat])
            ->callTableAction('tandai_terkirim', $surat)
            ->assertHasNoTableActionErrors()
            ->assertSee('Terkirim');

        $this->assertSame('terkirim', $surat->refresh()->status);
    }

    public function test_request_livewire_yang_memaksa_aksi_tersembunyi_tetap_tidak_dijalankan(): void
    {
        $this->actingAs($this->user(User::ROLE_KEPALA_SEKOLAH));
        $surat = $this->suratKeluar();

        Livewire::test(ListSuratKeluars::class)
            ->set('mountedTableActions', ['tandai_terkirim'])
            ->set('mountedTableActionsArguments', [[]])
            ->set('mountedTableActionsData', [[]])
            ->set('mountedTableActionRecord', (string) $surat->getKey())
            ->call('callMountedTableAction')
            ->assertOk();

        $this->assertSame('draf', $surat->refresh()->status);
    }

    public function test_dashboard_admin_hanya_menampilkan_modul_operasional_yang_boleh_dibaca(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin);

        Livewire::test(WorkspaceOverviewWidget::class)
            ->assertViewHas('modules', fn (array $modules): bool => array_column($modules, 'label') === [
                'Surat Masuk',
                'Surat Keluar',
                'Legalisir',
            ])
            ->assertViewHas('attention', fn (array $items): bool => count($items) === 1)
            ->assertViewHas('canViewSurat', true)
            ->assertViewHas('canCreateSuratMasuk', false)
            ->assertViewHas('canCreateSuratKeluar', false)
            ->assertSee('Surat yang perlu dilihat')
            ->assertDontSee('Catat surat masuk')
            ->assertDontSee('Buat surat keluar')
            ->assertSee('Kelola Pengguna');
    }

    public function test_endpoint_cetak_legalisir_memeriksa_permission_backend(): void
    {
        $legalisir = Legalisir::create([
            'nomor_legalisir' => 'LEG/2026/0999', 'nama_pemohon' => 'Alumni',
            'status_pemohon' => 'alumni', 'jenis_dokumen' => 'Ijazah',
            'tanggal_permohonan' => now(), 'status' => 'diterima',
        ]);

        $this->actingAs($this->user(User::ROLE_KEPEGAWAIAN));
        $this->get(route('legalisir.cetak-bukti', $legalisir))->assertForbidden();

        auth()->user()->update(['role' => User::ROLE_PETUGAS_TU]);
        $this->get(route('legalisir.cetak-bukti', $legalisir))->assertOk();
    }

    public function test_admin_tidak_dapat_menurunkan_role_atau_menonaktifkan_akunnya_sendiri(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm([
                'name' => 'Admin Diperbarui',
                'role' => User::ROLE_PETUGAS_TU,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertSame('Admin Diperbarui', $admin->name);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertFalse(UserResource::canDelete($admin));
    }

    private function user(string $role, ?string $email = null): User
    {
        return User::create([
            'name' => User::ROLE_LABELS[$role],
            'username' => $role.uniqid(),
            'email' => $email ?? $role.uniqid().'@test.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function suratKeluar(): SuratKeluar
    {
        return SuratKeluar::create([
            'nomor_surat' => '421/777/SIPATU/2026', 'nomor_urut' => 777,
            'tahun' => 2026, 'tanggal_surat' => now(), 'tujuan' => 'Dinas',
            'perihal' => 'Uji hak akses', 'status' => 'draf',
        ]);
    }
}
