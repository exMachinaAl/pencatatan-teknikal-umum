<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KenaikanPangkat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kenaikan_pangkat';

    protected $fillable = [
        'pegawai_id',
        'jenis',
        'golongan_lama_id',
        'golongan_baru_id',
        'periode',
        'tmt',
        'nomor_sk',
        'tanggal_sk',
        'pejabat_penetap',
        'status',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tmt' => 'date',
        'tanggal_sk' => 'date',
    ];

    public const STATUSES = [
        'berkas_disiapkan' => 'Berkas Disiapkan',
        'diajukan' => 'Diajukan',
        'sk_terbit' => 'SK Terbit',
        'diserahkan' => 'Diserahkan',
    ];

    public const JENIS_KP = [
        'reguler' => 'Reguler (4 Tahunan)',
        'pilihan' => 'Pilihan (Jabatan Fungsional)',
        'penyesuaian_ijazah' => 'Penyesuaian Ijazah',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function golonganLama(): BelongsTo
    {
        return $this->belongsTo(RefGolongan::class, 'golongan_lama_id');
    }

    public function golonganBaru(): BelongsTo
    {
        return $this->belongsTo(RefGolongan::class, 'golongan_baru_id');
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
