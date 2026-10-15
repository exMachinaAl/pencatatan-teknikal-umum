<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Referensi Golongan
        Schema::create('ref_golongan', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('kode', 10)->unique(); // I/a, III/a, IV/e
            $table->string('pangkat', 60);       // Juru Muda, Penata Muda, Pembina Utama
            $table->string('tingkat', 10)->nullable(); // I, II, III, IV
            $table->smallInteger('urutan')->default(0);
            $table->timestamps();
        });

        // 2. Referensi Klasifikasi Surat
        Schema::create('ref_klasifikasi_surat', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode', 30)->index();
            $table->string('nama', 150);
            $table->smallInteger('retensi_tahun')->nullable();
            $table->timestamps();
        });

        // 3. Pegawai
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nama', 150);
            $table->string('status_kepegawaian', 30)->default('PNS'); // PNS | PPPK | Honorer
            $table->string('jabatan', 120)->nullable();
            $table->unsignedSmallInteger('golongan_id')->nullable();
            $table->foreign('golongan_id')->references('id')->on('ref_golongan')->nullOnDelete();
            $table->date('tmt_pangkat_terakhir')->nullable();
            $table->date('tmt_kgb_terakhir')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('nama');
            $table->index('status_kepegawaian');
            $table->index('is_active');
        });

        // 4. Kenaikan Pangkat
        Schema::create('kenaikan_pangkat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('jenis', 30)->default('reguler'); // reguler | pilihan | penyesuaian_ijazah
            $table->unsignedSmallInteger('golongan_lama_id')->nullable();
            $table->foreign('golongan_lama_id')->references('id')->on('ref_golongan')->nullOnDelete();
            $table->unsignedSmallInteger('golongan_baru_id')->nullable();
            $table->foreign('golongan_baru_id')->references('id')->on('ref_golongan')->nullOnDelete();
            $table->string('periode', 30)->nullable(); // contoh: 2026-04
            $table->date('tmt')->nullable();
            $table->string('nomor_sk', 100)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('pejabat_penetap', 150)->nullable();
            $table->string('status', 30)->default('berkas_disiapkan'); // berkas_disiapkan | diajukan | sk_terbit | diserahkan
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('pegawai_id');
            $table->index(['status', 'periode']);
        });

        // 5. Gaji Berkala (KGB)
        Schema::create('gaji_berkala', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->unsignedSmallInteger('golongan_id')->nullable();
            $table->foreign('golongan_id')->references('id')->on('ref_golongan')->nullOnDelete();
            $table->date('tmt');
            $table->decimal('gaji_pokok_lama', 14, 2)->nullable();
            $table->decimal('gaji_pokok_baru', 14, 2)->nullable();
            $table->smallInteger('mk_tahun')->default(0);
            $table->smallInteger('mk_bulan')->default(0);
            $table->string('nomor_sk', 100)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('pejabat_penetap', 150)->nullable();
            $table->date('tmt_berikutnya')->nullable();
            $table->string('status', 30)->default('diproses'); // diproses | sk_terbit | diserahkan
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('pegawai_id');
            $table->index('tmt_berikutnya');
            $table->index('status');
        });

        // 6. Surat Masuk
        Schema::create('surat_masuk', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_agenda', 50)->unique();
            $table->string('nomor_surat', 100)->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->date('tanggal_diterima');
            $table->string('pengirim', 200);
            $table->string('perihal', 300);
            $table->string('sifat', 20)->default('biasa'); // biasa | penting | segera | rahasia
            $table->unsignedInteger('klasifikasi_id')->nullable();
            $table->foreign('klasifikasi_id')->references('id')->on('ref_klasifikasi_surat')->nullOnDelete();
            $table->string('status', 30)->default('baru'); // baru | didisposisi | selesai | diarsipkan
            $table->string('lokasi_arsip_fisik', 150)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('tautan_gform', 500)->nullable();
            $table->string('sumber', 30)->default('manual'); // manual | gform_import
            $table->timestamp('diarsipkan_pada')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal_diterima');
            $table->index('status');
            $table->index('pengirim');
        });

        // 7. Disposisi Surat Masuk
        Schema::create('disposisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_masuk_id')->constrained('surat_masuk')->cascadeOnDelete();
            $table->foreignId('dari_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kepada', 150);
            $table->text('instruksi')->nullable();
            $table->date('batas_waktu')->nullable();
            $table->string('status', 20)->default('terbuka'); // terbuka | diproses | selesai
            $table->text('catatan_tindak_lanjut')->nullable();
            $table->timestamps();

            $table->index('surat_masuk_id');
        });

        // 8. Surat Keluar
        Schema::create('surat_keluar', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat', 100)->unique();
            $table->integer('nomor_urut');
            $table->smallInteger('tahun');
            $table->date('tanggal_surat');
            $table->string('tujuan', 200);
            $table->string('perihal', 300);
            $table->string('sifat', 20)->default('biasa'); // biasa | penting | segera | rahasia
            $table->unsignedInteger('klasifikasi_id')->nullable();
            $table->foreign('klasifikasi_id')->references('id')->on('ref_klasifikasi_surat')->nullOnDelete();
            $table->string('penandatangan', 150)->nullable();
            $table->string('pembuat_konsep', 150)->nullable();
            $table->string('status', 30)->default('draf'); // draf | terkirim | diarsipkan
            $table->string('lokasi_arsip_fisik', 150)->nullable();
            $table->string('file_final_path', 500)->nullable();
            $table->string('tautan_gform', 500)->nullable();
            $table->string('sumber', 30)->default('manual'); // manual | gform_import
            $table->timestamp('diarsipkan_pada')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tahun', 'nomor_urut']);
            $table->index('tanggal_surat');
            $table->index('status');
        });

        // 9. Lampiran Polimorfik
        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();
            $table->string('entitas', 40); // surat_masuk | surat_keluar | kenaikan_pangkat | gaji_berkala | legalisir
            $table->unsignedBigInteger('entitas_id');
            $table->string('nama_file', 255);
            $table->string('path', 500);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('ukuran_byte')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entitas', 'entitas_id']);
        });

        // 10. Legalisir
        Schema::create('legalisir', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_legalisir', 50)->nullable()->unique();
            $table->string('nama_pemohon', 150);
            $table->string('kontak', 60)->nullable();
            $table->string('status_pemohon', 30)->default('alumni'); // alumni | siswa | wali | lainnya
            $table->string('jenis_dokumen', 100); // Ijazah | SKHUN | Transkrip Nilai | Rapor | Lainnya
            $table->smallInteger('jumlah_lembar')->default(1);
            $table->date('tanggal_permohonan');
            $table->date('tanggal_selesai')->nullable();
            $table->date('tanggal_diambil')->nullable();
            $table->decimal('biaya', 12, 2)->default(0);
            $table->string('pengambil', 150)->nullable();
            $table->string('status', 30)->default('diterima'); // diterima | diproses | siap_diambil | diambil | ditolak
            $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->string('file_dokumen_path', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tanggal_permohonan', 'status']);
        });

        // 11. Log Impor Google Form
        Schema::create('import_gform_log', function (Blueprint $table) {
            $table->id();
            $table->string('modul', 40); // surat_masuk | surat_keluar | legalisir
            $table->string('nama_file', 255);
            $table->integer('baris_total')->default(0);
            $table->integer('baris_berhasil')->default(0);
            $table->integer('baris_gagal')->default(0);
            $table->text('detail_error')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 12. Audit Log
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 150)->nullable();
            $table->string('aksi', 30); // create | update | delete | restore | login | export | import
            $table->string('entitas', 50)->nullable();
            $table->unsignedBigInteger('entitas_id')->nullable();
            $table->json('data_sebelum')->nullable();
            $table->json('data_sesudah')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entitas', 'entitas_id']);
            $table->index('user_id');
            $table->index('aksi');
        });

        // 13. Pengaturan Aplikasi (App Settings)
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('import_gform_log');
        Schema::dropIfExists('legalisir');
        Schema::dropIfExists('lampiran');
        Schema::dropIfExists('surat_keluar');
        Schema::dropIfExists('disposisi');
        Schema::dropIfExists('surat_masuk');
        Schema::dropIfExists('gaji_berkala');
        Schema::dropIfExists('kenaikan_pangkat');
        Schema::dropIfExists('pegawai');
        Schema::dropIfExists('ref_klasifikasi_surat');
        Schema::dropIfExists('ref_golongan');
    }
};
