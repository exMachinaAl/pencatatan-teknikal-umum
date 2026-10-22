<?php

namespace Tests\Feature;

use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use App\Models\User;
use App\Services\TuPersonnelExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PersonnelExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_mengikuti_input_kepegawaian_terbaru(): void
    {
        $this->assertSame(
            ['nip', 'nama', 'status_kepegawaian', 'golongan_kode'],
            TuPersonnelExchange::template('pegawai')[0],
        );
        $this->assertSame(
            ['pegawai_nip', 'pegawai_nama', 'jenis', 'golongan_lama_kode', 'golongan_baru_kode', 'mk_tahun', 'mk_bulan', 'tautan_lampiran'],
            TuPersonnelExchange::template('kp')[0],
        );
        $this->assertSame(
            ['pegawai_nip', 'pegawai_nama', 'golongan_kode', 'mk_tahun', 'mk_bulan', 'tautan_lampiran'],
            TuPersonnelExchange::template('kgb')[0],
        );
    }

    public function test_impor_dan_ekspor_semua_modul_memakai_struktur_terbaru(): void
    {
        $golonganLama = $this->golongan('III/a', 1);
        $golonganBaru = $this->golongan('III/b', 2);
        $user = User::create([
            'name' => 'Petugas Kepegawaian',
            'username' => 'uji_exchange',
            'password' => 'password',
            'role' => User::ROLE_KEPEGAWAIAN,
            'is_active' => true,
        ]);

        $pegawaiFile = $this->csv([
            ['nip', 'nama', 'status_kepegawaian', 'golongan_kode'],
            ['198001012006041001', 'Contoh Pegawai, S.Pd', 'PNS', 'III/a'],
        ]);
        $kpFile = $this->csv([
            ['pegawai_nip', 'pegawai_nama', 'jenis', 'golongan_lama_kode', 'golongan_baru_kode', 'mk_tahun', 'mk_bulan', 'tautan_lampiran'],
            ['198001012006041001', 'Contoh Pegawai, S.Pd', 'reguler', 'III/a', 'III/b', '8', '6', 'https://drive.google.com/file/d/kp/view'],
        ]);
        $kgbFile = $this->csv([
            ['pegawai_nip', 'pegawai_nama', 'golongan_kode', 'mk_tahun', 'mk_bulan', 'tautan_lampiran'],
            ['198001012006041001', 'Contoh Pegawai, S.Pd', 'III/b', '10', '2', 'https://docs.google.com/spreadsheets/d/kgb/edit'],
        ]);

        try {
            $this->assertSame(1, TuPersonnelExchange::import('pegawai', $pegawaiFile, $user->id)['success']);
            $this->assertSame(1, TuPersonnelExchange::import('kp', $kpFile, $user->id)['success']);
            $this->assertSame(1, TuPersonnelExchange::import('kgb', $kgbFile, $user->id)['success']);
        } finally {
            @unlink($pegawaiFile);
            @unlink($kpFile);
            @unlink($kgbFile);
        }

        $pegawai = Pegawai::where('nip', '198001012006041001')->firstOrFail();
        $this->assertSame($golonganLama->id, $pegawai->golongan_id);
        $this->assertDatabaseHas('kenaikan_pangkat', [
            'pegawai_id' => $pegawai->id,
            'golongan_lama_id' => $golonganLama->id,
            'golongan_baru_id' => $golonganBaru->id,
            'mk_tahun' => 8,
            'mk_bulan' => 6,
        ]);
        $this->assertDatabaseHas('gaji_berkala', [
            'pegawai_id' => $pegawai->id,
            'golongan_id' => $golonganBaru->id,
            'mk_tahun' => 10,
            'mk_bulan' => 2,
        ]);

        $this->assertSame([
            ['nip', 'nama', 'status_kepegawaian', 'golongan_kode'],
            ['198001012006041001', 'Contoh Pegawai, S.Pd', 'PNS', 'III/a'],
        ], TuPersonnelExchange::export('pegawai', '198001012006041001', null, null));

        $this->assertSame('https://drive.google.com/file/d/kp/view', KenaikanPangkat::firstOrFail()->tautan_lampiran);
        $this->assertSame('https://docs.google.com/spreadsheets/d/kgb/edit', GajiBerkala::firstOrFail()->tautan_lampiran);
    }

    public function test_template_pegawai_lama_ditolak_dengan_pesan_upgrade(): void
    {
        $file = $this->csv([
            ['nip', 'nama', 'email', 'status_kepegawaian'],
            ['198001012006041001', 'Contoh Pegawai', 'pegawai@example.test', 'PNS'],
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Kolom tidak didukung oleh versi terbaru: email. Unduh template terbaru dari aplikasi.');
            TuPersonnelExchange::analyze('pegawai', $file);
        } finally {
            @unlink($file);
        }
    }

    private function golongan(string $kode, int $urutan): RefGolongan
    {
        return RefGolongan::create([
            'kode' => $kode,
            'pangkat' => 'Pangkat '.$kode,
            'tingkat' => 'III',
            'urutan' => $urutan,
        ]);
    }

    /** @param array<int, array<int, string>> $rows */
    private function csv(array $rows): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tu-personnel-'.uniqid().'.csv';
        $stream = fopen($path, 'wb');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        fclose($stream);

        return $path;
    }
}
