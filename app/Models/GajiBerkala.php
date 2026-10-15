<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GajiBerkala extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'gaji_berkala';

    protected $fillable = [
        'pegawai_id',
        'golongan_id',
        'tmt',
        'gaji_pokok_lama',
        'gaji_pokok_baru',
        'mk_tahun',
        'mk_bulan',
        'nomor_sk',
        'tanggal_sk',
        'pejabat_penetap',
        'tmt_berikutnya',
        'status',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tmt' => 'date',
        'tanggal_sk' => 'date',
        'tmt_berikutnya' => 'date',
        'gaji_pokok_lama' => 'decimal:2',
        'gaji_pokok_baru' => 'decimal:2',
    ];

    public const STATUSES = [
        'diproses' => 'Diproses',
        'sk_terbit' => 'SK Terbit',
        'diserahkan' => 'Diserahkan',
    ];

    protected static function booted()
    {
        static::saving(function (GajiBerkala $model) {
            // Jika tmt_berikutnya kosong dan tmt ada, default otomatis +2 tahun
            if (! $model->tmt_berikutnya && $model->tmt) {
                $model->tmt_berikutnya = Carbon::parse($model->tmt)->addYears(2);
            }
        });
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function golongan(): BelongsTo
    {
        return $this->belongsTo(RefGolongan::class, 'golongan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }

    // Scope jatuh tempo dalam rentang bulan tertentu dari sekarang
    public function scopeJatuhTempo(Builder $query, int $bulan = 3): Builder
    {
        return $query->whereNotNull('tmt_berikutnya')
            ->whereDate('tmt_berikutnya', '<=', now()->addMonths($bulan)->toDateString())
            ->whereDate('tmt_berikutnya', '>=', now()->subMonth()->toDateString());
    }

    public function scopePerluTindakLanjut(Builder $query): Builder
    {
        return $query->jatuhTempo()->where('status', '!=', 'diserahkan');
    }
}
