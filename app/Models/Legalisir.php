<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Legalisir extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'legalisir';

    protected $fillable = [
        'nomor_legalisir',
        'nama_pemohon',
        'kontak',
        'status_pemohon',
        'jenis_dokumen',
        'jumlah_lembar',
        'tanggal_permohonan',
        'tanggal_selesai',
        'tanggal_diambil',
        'biaya',
        'pengambil',
        'status',
        'petugas_id',
        'catatan',
        'file_dokumen_path',
    ];

    protected $casts = [
        'tanggal_permohonan' => 'date',
        'tanggal_selesai' => 'date',
        'tanggal_diambil' => 'date',
        'biaya' => 'decimal:2',
        'jumlah_lembar' => 'integer',
    ];

    public const STATUS_PEMOHON = [
        'alumni' => 'Alumni',
        'siswa' => 'Siswa Aktif',
        'wali' => 'Orang Tua / Wali',
        'lainnya' => 'Lainnya / Instansi',
    ];

    public const STATUSES = [
        'diterima' => 'Permohonan Diterima',
        'diproses' => 'Sedang Diverifikasi / Diproses',
        'siap_diambil' => 'Selesai & Siap Diambil',
        'diambil' => 'Sudah Diambil',
        'ditolak' => 'Ditolak / Berkas Tidak Sesuai',
    ];

    public const JENIS_DOKUMEN = [
        'Ijazah' => 'Ijazah SMA',
        'SKHUN' => 'SKHUN',
        'Transkrip Nilai' => 'Transkrip Nilai / Rapor Semester',
        'Surat Keterangan Pengganti Ijazah' => 'Surat Keterangan Pengganti Ijazah',
        'Sertifikat Akreditasi' => 'Sertifikat Akreditasi Sekolah',
        'Lainnya' => 'Dokumen Lainnya',
    ];

    public static function generateNomorLegalisir(?int $tahun = null): string
    {
        $tahun = $tahun ?? (int) date('Y');

        return DB::transaction(function () use ($tahun) {
            $count = static::whereYear('tanggal_permohonan', $tahun)->count();
            $next = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $nomor = "LEG/{$tahun}/{$next}";

            while (static::where('nomor_legalisir', $nomor)->exists()) {
                $count++;
                $next = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                $nomor = "LEG/{$tahun}/{$next}";
            }

            return $nomor;
        });
    }

    public function scopePerluTindakLanjut(Builder $query): Builder
    {
        return $query->whereIn('status', ['diterima', 'diproses']);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }
}
