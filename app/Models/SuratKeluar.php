<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SuratKeluar extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * Dihasilkan di dalam transaksi database untuk mencegah nomor ganda (race condition)
     */
    public static function generateNomorSurat(?int $tahun = null, ?string $kodeKlasifikasi = null): array
    {
        $tahun = $tahun ?? (int) date('Y');

        return DB::transaction(function () use ($tahun, $kodeKlasifikasi) {
            $lastSurat = static::where('tahun', $tahun)
                ->lockForUpdate()
                ->orderByDesc('nomor_urut')
                ->first();

            $nomorUrut = $lastSurat ? ($lastSurat->nomor_urut + 1) : 1;
            $nomorFormatted = str_pad($nomorUrut, 3, '0', STR_PAD_LEFT);

            // Format konfigurasi dari AppSetting atau default
            $template = AppSetting::get('format_surat_keluar', '421/{nomor_urut}/SMAN1/{tahun}');
            $kode = $kodeKlasifikasi ?: '421';

            $nomorSurat = str_replace(
                ['{nomor_urut}', '{tahun}', '{kode_klasifikasi}'],
                [$nomorFormatted, $tahun, $kode],
                $template
            );

            return [
                'nomor_urut' => $nomorUrut,
                'tahun' => $tahun,
                'nomor_surat' => $nomorSurat,
            ];
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
}
