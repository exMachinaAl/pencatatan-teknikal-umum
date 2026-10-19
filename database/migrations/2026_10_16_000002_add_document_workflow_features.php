<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lampiran', function (Blueprint $table) {
            $table->unsignedSmallInteger('versi')->default(1)->after('path');
            $table->boolean('is_current')->default(true)->after('versi');
            $table->string('akses', 20)->default('internal')->after('is_current');
            $table->text('keterangan')->nullable()->after('akses');
            $table->index(['entitas', 'entitas_id', 'is_current'], 'lampiran_current_index');
        });

        Schema::create('lampiran_access_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lampiran_id')->constrained('lampiran')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 30)->default('download');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['lampiran_id', 'created_at']);
        });

        Schema::table('disposisi', function (Blueprint $table) {
            $table->foreignId('penanggung_jawab_id')->nullable()->after('kepada')->constrained('users')->nullOnDelete();
            $table->string('prioritas', 20)->default('normal')->after('instruksi');
            $table->timestamp('selesai_pada')->nullable()->after('status');
            $table->index(['status', 'batas_waktu'], 'disposisi_deadline_index');
        });

        Schema::create('relasi_surat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_masuk_id')->constrained('surat_masuk')->cascadeOnDelete();
            $table->foreignId('surat_keluar_id')->constrained('surat_keluar')->cascadeOnDelete();
            $table->string('jenis_relasi', 30)->default('balasan');
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['surat_masuk_id', 'surat_keluar_id']);
        });

        Schema::table('surat_keluar', function (Blueprint $table) {
            $table->string('metode_pengiriman', 40)->nullable()->after('status');
            $table->string('nomor_resi', 100)->nullable()->after('metode_pengiriman');
            $table->timestamp('dikirim_pada')->nullable()->after('nomor_resi');
            $table->timestamp('diterima_pada')->nullable()->after('dikirim_pada');
            $table->string('diterima_oleh', 150)->nullable()->after('diterima_pada');
            $table->string('bukti_pengiriman_path', 500)->nullable()->after('diterima_oleh');
            $table->index('nomor_resi');
        });

        Schema::table('legalisir', function (Blueprint $table) {
            $table->string('nomor_antrean', 40)->nullable()->unique()->after('nomor_legalisir');
            $table->string('tracking_token_hash', 64)->nullable()->unique()->after('nomor_antrean');
            $table->text('tracking_token_encrypted')->nullable()->after('tracking_token_hash');
            $table->string('email_pemohon', 150)->nullable()->after('kontak');
            $table->string('kanal_notifikasi', 20)->default('tidak_ada')->after('email_pemohon');
            $table->string('nomor_dokumen', 120)->nullable()->after('jenis_dokumen');
            $table->smallInteger('tahun_lulus')->nullable()->after('nomor_dokumen');
            $table->boolean('dokumen_asli_dilihat')->default(false)->after('jumlah_lembar');
            $table->boolean('identitas_cocok')->default(false)->after('dokumen_asli_dilihat');
            $table->boolean('data_sekolah_cocok')->default(false)->after('identitas_cocok');
            $table->foreignId('diverifikasi_oleh')->nullable()->after('petugas_id')->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh');
            $table->timestamp('jadwal_pengambilan')->nullable()->after('tanggal_selesai');
            $table->string('hubungan_pengambil', 100)->nullable()->after('pengambil');
            $table->string('bukti_serah_terima_path', 500)->nullable()->after('hubungan_pengambil');
            $table->text('alasan_penolakan')->nullable()->after('catatan');
        });

        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('entitas', 50);
            $table->unsignedBigInteger('entitas_id');
            $table->string('kanal', 20);
            $table->string('tujuan', 190);
            $table->string('template', 80);
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('antre');
            $table->unsignedSmallInteger('percobaan')->default(0);
            $table->text('error_terakhir')->nullable();
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['entitas', 'entitas_id']);
        });

        $queueCounters = [];
        DB::table('legalisir')->orderBy('tanggal_permohonan')->orderBy('id')->get()->each(function ($row) use (&$queueCounters): void {
            $date = substr((string) $row->tanggal_permohonan, 0, 10) ?: now()->format('Y-m-d');
            $dateKey = str_replace('-', '', $date);
            $queueCounters[$dateKey] = ($queueCounters[$dateKey] ?? 0) + 1;
            $token = Str::random(48);

            DB::table('legalisir')->where('id', $row->id)->update([
                'nomor_antrean' => 'L-'.$dateKey.'-'.str_pad((string) $queueCounters[$dateKey], 3, '0', STR_PAD_LEFT),
                'tracking_token_hash' => hash('sha256', $token),
                'tracking_token_encrypted' => Crypt::encryptString($token),
            ]);
        });

        $existingFiles = [
            ['table' => 'surat_masuk', 'field' => 'file_path', 'entity' => 'SuratMasuk', 'label' => 'Berkas utama'],
            ['table' => 'surat_keluar', 'field' => 'file_final_path', 'entity' => 'SuratKeluar', 'label' => 'Berkas final'],
            ['table' => 'legalisir', 'field' => 'file_dokumen_path', 'entity' => 'Legalisir', 'label' => 'Dokumen verifikasi'],
        ];

        foreach ($existingFiles as $file) {
            DB::table($file['table'])->whereNotNull($file['field'])->orderBy('id')->get()->each(function ($row) use ($file): void {
                $path = $row->{$file['field']};
                DB::table('lampiran')->insert([
                    'entitas' => $file['entity'],
                    'entitas_id' => $row->id,
                    'nama_file' => basename($path),
                    'path' => $path,
                    'versi' => 1,
                    'is_current' => true,
                    'akses' => 'internal',
                    'keterangan' => $file['label'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');

        Schema::table('legalisir', function (Blueprint $table) {
            $table->dropForeign(['diverifikasi_oleh']);
            $table->dropColumn([
                'nomor_antrean', 'tracking_token_hash', 'tracking_token_encrypted',
                'email_pemohon', 'kanal_notifikasi', 'nomor_dokumen', 'tahun_lulus',
                'dokumen_asli_dilihat', 'identitas_cocok', 'data_sekolah_cocok',
                'diverifikasi_oleh', 'diverifikasi_pada', 'jadwal_pengambilan',
                'hubungan_pengambil', 'bukti_serah_terima_path', 'alasan_penolakan',
            ]);
        });

        Schema::table('surat_keluar', function (Blueprint $table) {
            $table->dropColumn([
                'metode_pengiriman', 'nomor_resi', 'dikirim_pada', 'diterima_pada',
                'diterima_oleh', 'bukti_pengiriman_path',
            ]);
        });

        Schema::dropIfExists('relasi_surat');

        Schema::table('disposisi', function (Blueprint $table) {
            $table->dropForeign(['penanggung_jawab_id']);
            $table->dropColumn(['penanggung_jawab_id', 'prioritas', 'selesai_pada']);
        });

        Schema::dropIfExists('lampiran_access_log');

        Schema::table('lampiran', function (Blueprint $table) {
            $table->dropIndex('lampiran_current_index');
            $table->dropColumn(['versi', 'is_current', 'akses', 'keterangan']);
        });
    }
};
