<?php

namespace App\Models;

use App\Models\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SuratKeluar extends Model
{
    use HasFactory, HasOptimisticLock, SoftDeletes;

    protected $table = 'surat_keluar';

    protected $fillable = [
        'nomor_surat',
        'nomor_urut',
        'tahun',
        'tanggal_surat',
        'tujuan',
        'perihal',
        'sifat',
        'klasifikasi_id',
        'penandatangan',
        'pembuat_konsep',
        'status',
        'metode_pengiriman',
        'nomor_resi',
        'dikirim_pada',
        'diterima_pada',
        'diterima_oleh',
        'bukti_pengiriman_path',
        'lokasi_arsip_fisik',
        'file_final_path',
        'tautan_gform',
        'sumber',
        'diarsipkan_pada',
        'created_by',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'diarsipkan_pada' => 'datetime',
        'dikirim_pada' => 'datetime',
        'diterima_pada' => 'datetime',
        'nomor_urut' => 'integer',
        'tahun' => 'integer',
    ];

    public const SIFAT = [
        'biasa' => 'Biasa',
        'penting' => 'Penting',
        'segera' => 'Segera',
        'rahasia' => 'Rahasia',
    ];

    public const STATUSES = [
        'draf' => 'Konsep / Draf',
        'terkirim' => 'Terkirim',
        'diarsipkan' => 'Diarsipkan',
    ];

    protected static function booted(): void
    {
        static::saving(function (SuratKeluar $surat): void {
            $surat->perihal ??= '';
            $surat->sifat ??= 'biasa';
            $surat->status ??= 'draf';

            $surat->nomor_surat = trim((string) $surat->nomor_surat);

            if ($surat->nomor_surat === '') {
                throw ValidationException::withMessages(['nomor_surat' => 'Nomor surat resmi wajib diisi.']);
            }

            $nomorSudahDipakai = static::withTrashed()
                ->where('nomor_surat', $surat->nomor_surat)
                ->when($surat->exists, fn ($query) => $query->whereKeyNot($surat->getKey()))
                ->exists();

            if ($nomorSudahDipakai) {
                throw ValidationException::withMessages(['nomor_surat' => 'Nomor surat resmi sudah tercatat.']);
            }

            if (! $surat->tanggal_surat) {
                throw ValidationException::withMessages(['tanggal_surat' => 'Tanggal surat wajib diisi.']);
            }

            $surat->tahun = Carbon::parse($surat->tanggal_surat)->year;

            if (! array_key_exists((string) $surat->sifat, self::SIFAT)) {
                throw ValidationException::withMessages(['sifat' => 'Sifat surat keluar tidak valid.']);
            }

            if (! array_key_exists((string) $surat->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => 'Status surat keluar tidak valid.']);
            }
        });

        static::updating(function (SuratKeluar $surat): void {
            if ($surat->isDirty('nomor_urut')) {
                throw ValidationException::withMessages(['nomor_urut' => 'Nomor urut lama tidak dapat diubah.']);
            }
        });
    }

    public function klasifikasi(): BelongsTo
    {
        return $this->belongsTo(RefKlasifikasiSurat::class, 'klasifikasi_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }

    public function suratMasukTerkait(): BelongsToMany
    {
        return $this->belongsToMany(SuratMasuk::class, 'relasi_surat', 'surat_keluar_id', 'surat_masuk_id')
            ->withPivot(['jenis_relasi', 'catatan', 'created_by'])
            ->withTimestamps();
    }
}
