<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupManager;
use App\Filament\Resources\GajiBerkalaResource\Pages\ListGajiBerkalas;
use App\Filament\Resources\LegalisirResource\Pages\ListLegalisirs;
use App\Filament\Resources\PegawaiResource\Pages\ListPegawais;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\WorkspaceOverviewWidget;
use App\Models\GajiBerkala;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'operator@test.com',
            'password' => 'password', 'role' => User::ROLE_KEPALA_TU, 'is_active' => true,
        ]));
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    }

    public function test_prioritas_hanya_surat_aktif_dan_mendahulukan_urgensi_lalu_tanggal(): void
    {
        $this->incoming('arsip', 'diarsipkan', 'segera', '2026-10-15');
        $this->incoming('selesai', 'selesai', 'segera', '2026-10-15');
        $this->incoming('biasa', 'baru', 'biasa', '2026-10-15');
        $this->incoming('urgent-lama', 'baru', 'segera', '2026-10-10');
        $incoming = $this->incoming('urgent-terbaru', 'didisposisi', 'segera', '2026-10-14');
        $deleted = $this->incoming('terhapus', 'baru', 'segera', '2026-10-15');
        $deleted->delete();

        $this->outgoing(1, 'terkirim', 'segera', '2026-10-15');
        $this->outgoing(2, 'diarsipkan', 'segera', '2026-10-15');
        $this->outgoing(3, 'draf', 'biasa', '2026-10-15');
        $this->outgoing(4, 'draf', 'segera', '2026-10-10');
        $outgoing = $this->outgoing(5, 'draf', 'segera', '2026-10-14');

        Livewire::test(WorkspaceOverviewWidget::class)
            ->assertViewHas('suratMasuk', fn ($record) => $record->is($incoming))
            ->assertViewHas('suratKeluar', fn ($record) => $record->is($outgoing))
            ->assertSee('urgent-terbaru')
            ->assertDontSee('urgent-lama');
    }

    public function test_keadaan_kosong_tidak_menyamakan_arsip_dengan_belum_ada_data(): void
    {
        $this->incoming('arsip', 'diarsipkan', 'segera', '2026-10-15');
        $this->outgoing(1, 'terkirim', 'segera', '2026-10-15');

        Livewire::test(WorkspaceOverviewWidget::class)
            ->assertViewHas('suratMasuk', null)
            ->assertViewHas('suratKeluar', null)
            ->assertSee('Tidak ada surat masuk yang menunggu')
            ->assertSee('Tidak ada konsep yang menunggu')
            ->assertSee('Lihat semua surat masuk')
            ->assertSee('Lihat semua surat keluar');
    }

    public function test_jumlah_tindak_lanjut_sesuai_filter_tujuan(): void
    {
        $pending = collect(['diterima', 'diproses'])->map(fn ($status) => $this->legalisir($status));
        $completed = $this->legalisir('diambil');

        $pegawai = Pegawai::create(['nama' => 'Calon aktif', 'tmt_pangkat_terakhir' => '2022-04-15', 'is_active' => true]);
        $inactive = Pegawai::create(['nama' => 'Calon nonaktif', 'tmt_pangkat_terakhir' => '2022-04-15', 'is_active' => false]);
        $future = Pegawai::create(['nama' => 'Belum masuk periode', 'tmt_pangkat_terakhir' => '2026-01-01']);

        // Kedua batas tanggal termasuk, terlepas dari jam saat query dijalankan.
        $kgb = collect(['2026-09-15', '2027-01-15'])->values()->map(fn ($date, $index) => GajiBerkala::create([
            'pegawai_id' => $pegawai->id, 'tmt' => '2024-0'.($index + 1).'-01',
            'tmt_berikutnya' => $date, 'status' => 'diproses',
        ]));
        $delivered = GajiBerkala::create([
            'pegawai_id' => $pegawai->id, 'tmt' => '2024-03-01',
            'tmt_berikutnya' => '2026-10-15', 'status' => 'diserahkan',
        ]);
        $outside = GajiBerkala::create([
            'pegawai_id' => $pegawai->id, 'tmt' => '2024-04-01',
            'tmt_berikutnya' => '2027-01-16', 'status' => 'diproses',
        ]);

        $attention = [];
        Livewire::test(WorkspaceOverviewWidget::class)
            ->assertViewHas('attention', function ($items) use (&$attention) {
                $attention = $items;
                return array_column($items, 'count') === [2, 2, 1];
            });

        $params = function (int $index) use ($attention): array {
            parse_str(parse_url($attention[$index]['url'], PHP_URL_QUERY), $query);
            return $query;
        };

        Livewire::withQueryParams($params(0))->test(ListLegalisirs::class)
            ->assertCanSeeTableRecords($pending)
            ->assertCanNotSeeTableRecords([$completed]);

        Livewire::withQueryParams($params(1))->test(ListGajiBerkalas::class)
            ->assertCanSeeTableRecords($kgb)
            ->assertCanNotSeeTableRecords([$delivered, $outside]);

        Livewire::withQueryParams($params(2))->test(ListPegawais::class)
            ->assertCanSeeTableRecords([$pegawai])
            ->assertCanNotSeeTableRecords([$inactive, $future]);
    }

    public function test_utilitas_admin_tidak_ditawarkan_ke_staf(): void
    {
        auth()->user()->update(['role' => User::ROLE_ADMIN]);
        $adminUrls = [UserResource::getUrl(), BackupManager::getUrl()];
        Livewire::test(WorkspaceOverviewWidget::class)
            ->assertViewHas('utilities', fn ($items) => count(array_intersect($adminUrls, array_column($items, 'url'))) === 2);

        auth()->user()->update(['role' => User::ROLE_PETUGAS_TU]);
        Livewire::test(WorkspaceOverviewWidget::class)
            ->assertViewHas('utilities', fn ($items) => count(array_intersect($adminUrls, array_column($items, 'url'))) === 0);
    }

    private function incoming(string $reference, string $status, string $sifat, string $date): SuratMasuk
    {
        return SuratMasuk::create([
            'nomor_agenda' => $reference, 'perihal' => $reference, 'pengirim' => 'Dinas',
            'tanggal_diterima' => $date, 'status' => $status, 'sifat' => $sifat,
        ]);
    }

    private function outgoing(int $number, string $status, string $sifat, string $date): SuratKeluar
    {
        return SuratKeluar::create([
            'nomor_surat' => "SK/2026/{$number}", 'nomor_urut' => $number, 'tahun' => 2026,
            'perihal' => "Konsep {$number}", 'tujuan' => 'Dinas', 'tanggal_surat' => $date,
            'status' => $status, 'sifat' => $sifat,
        ]);
    }

    private function legalisir(string $status): Legalisir
    {
        return Legalisir::create([
            'nomor_legalisir' => "LEG/{$status}", 'nama_pemohon' => "Pemohon {$status}",
            'jenis_dokumen' => 'Ijazah', 'status_pemohon' => 'alumni',
            'tanggal_permohonan' => '2026-10-15', 'status' => $status,
        ]);
    }
}
