<?php

namespace Tests\Feature;

use App\Filament\Resources\SuratMasukResource\Pages\EditSuratMasuk;
use App\Models\AuditLog;
use App\Models\Legalisir;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Support\SecureCsv;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_lampiran_menolak_path_backup_dan_penggunaan_ulang_antar_record(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/backup-dummy.sql', 'dummy');

        try {
            SuratMasuk::create([
                'tanggal_diterima' => today(),
                'pengirim' => 'Uji Path Backup',
                'file_path' => 'backups/backup-dummy.sql',
            ]);
            $this->fail('Path backup seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file_path', $exception->errors());
        }

        Storage::disk('local')->put('arsip_surat_masuk/sah.pdf', 'dummy pdf');
        $first = SuratMasuk::create([
            'tanggal_diterima' => today(),
            'pengirim' => 'Pemilik Lampiran',
            'file_path' => 'arsip_surat_masuk/sah.pdf',
        ]);
        $this->assertDatabaseHas('lampiran', ['entitas_id' => $first->id, 'path' => 'arsip_surat_masuk/sah.pdf']);

        $this->expectException(ValidationException::class);
        SuratMasuk::create([
            'tanggal_diterima' => today(),
            'pengirim' => 'Pengguna Ulang',
            'file_path' => 'arsip_surat_masuk/sah.pdf',
        ]);
    }

    public function test_form_edit_lama_ditolak_dan_perubahan_pengguna_lain_dipertahankan(): void
    {
        $operatorA = $this->operator('operator.alfa');
        $operatorB = $this->operator('operator.beta');
        $record = SuratMasuk::create(['tanggal_diterima' => today(), 'pengirim' => 'Awal']);

        $this->actingAs($operatorB);
        $staleForm = Livewire::test(EditSuratMasuk::class, ['record' => $record->id]);

        $this->actingAs($operatorA);
        $record->update(['pengirim' => 'Perubahan Alfa']);

        $this->actingAs($operatorB);
        $staleForm->fillForm(['lokasi_arsip_fisik' => 'Loker Beta'])
            ->call('save')
            ->assertHasErrors(['data']);

        $this->assertSame('Perubahan Alfa', $record->fresh()->pengirim);
        $this->assertNull($record->fresh()->lokasi_arsip_fisik);
    }

    public function test_perubahan_dibatalkan_jika_audit_log_gagal_disimpan(): void
    {
        $this->actingAs($this->operator('operator.audit'));
        $record = SuratMasuk::create(['tanggal_diterima' => today(), 'pengirim' => 'Sebelum']);
        $component = Livewire::test(EditSuratMasuk::class, ['record' => $record->id])
            ->fillForm(['pengirim' => 'Sesudah']);

        AuditLog::creating(function (AuditLog $log): void {
            if ($log->aksi === 'update' && $log->entitas === 'SuratMasuk') {
                throw new \RuntimeException('SIMULASI_AUDIT_GAGAL');
            }
        });

        try {
            $component->call('save');
            $this->fail('Kegagalan audit seharusnya membatalkan simpan.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('SIMULASI_AUDIT_GAGAL', $exception->getMessage());
        }

        $this->assertSame('Sebelum', $record->fresh()->pengirim);
    }

    public function test_status_legalisir_siap_ditolak_tanpa_verifikasi(): void
    {
        $this->actingAs($this->operator('operator.legalisir'));
        $this->expectException(ValidationException::class);

        Legalisir::create([
            'tanggal_permohonan' => today(),
            'nama_pemohon' => 'Pemohon Uji',
            'jenis_dokumen' => 'Ijazah',
            'jumlah_lembar' => 1,
            'status' => 'siap_diambil',
        ]);
    }

    public function test_username_angka_ditolak_agar_tidak_mengambil_prioritas_nip(): void
    {
        $this->expectException(ValidationException::class);
        User::create([
            'name' => 'Username Angka',
            'username' => '198001012005011001',
            'password' => 'password-yang-aman',
            'role' => User::ROLE_PETUGAS_TU,
            'is_active' => true,
        ]);
    }

    public function test_semua_nilai_csv_menonaktifkan_awalan_formula(): void
    {
        foreach (['=1+1', '+SUM(A1)', '-2+3', '@cmd', " \t=HYPERLINK(\"x\")"] as $formula) {
            $this->assertSame("'".$formula, SecureCsv::value($formula));
        }
        $this->assertSame('08123456789', SecureCsv::value('08123456789'));
    }

    public function test_login_berhasil_mencatat_snapshot_identitas_akun(): void
    {
        $user = $this->operator('operator.login');
        event(new Login('web', $user, false));

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_username' => $user->username,
            'user_role' => User::ROLE_PETUGAS_TU,
            'aksi' => 'login_berhasil',
        ]);
    }

    private function operator(string $username): User
    {
        return User::create([
            'name' => 'Petugas '.$username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password-yang-aman',
            'role' => User::ROLE_PETUGAS_TU,
            'is_active' => true,
        ]);
    }
}
