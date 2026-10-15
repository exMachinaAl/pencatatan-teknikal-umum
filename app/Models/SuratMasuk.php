<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SuratMasuk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surat_masuk';

    protected $fillable = [
        'nomor_agenda',
        'nomor_surat',
        'tanggal_surat',
        'tanggal_diterima',
        'pengirim',
        'perihal',
        'sifat',
        'klasifikasi_id',
        'status',
        'lokasi_arsip_fisik',
        'file_path',
        'tautan_gform',
        'sumber',
        'diarsipkan_pada',
        'created_by',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'tanggal_diterima' => 'date',
        'diarsipkan_pada' => 'datetime',
    ];

    public const SIFAT = [
        'biasa' => 'Biasa',
        'penting' => 'Penting',
        'segera' => 'Segera',
        'rahasia' => 'Rahasia',
    ];

    public const STATUSES = [
        'baru' => 'Baru Diterima',
        'didisposisi' => 'Didisposisikan',
        'selesai' => 'Selesai Ditindaklanjuti',
        'diarsipkan' => 'Diarsipkan',
    ];

    public static function generateNomorAgenda(?int $tahun = null): string
    {
        $tahun = $tahun ?? (int) date('Y');

        return DB::transaction(function () use ($tahun) {
            // Hitung surat masuk pada tahun ini
            $count = static::whereYear('tanggal_diterima', $tahun)->count();
            $nextNumber = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            $nomorAgenda = "SM/{$tahun}/{$nextNumber}";

            // Pastikan jika ada tabrakan
            while (static::where('nomor_agenda', $nomorAgenda)->exists()) {
                $count++;
                $nextNumber = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                $nomorAgenda = "SM/{$tahun}/{$nextNumber}";
            }

            return $nomorAgenda;
        });
    }

    public function klasifikasi(): BelongsTo
    {
        return $this->belongsTo(RefKlasifikasiSurat::class, 'klasifikasi_id');
    }

    public function disposisi(): HasMany
    {
        return $this->hasMany(Disposisi::class, 'surat_masuk_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }
}
