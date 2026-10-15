<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pegawai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pegawai';

    protected $fillable = [
        'nip',
        'nama',
        'status_kepegawaian',
        'jabatan',
        'golongan_id',
        'tmt_pangkat_terakhir',
        'tmt_kgb_terakhir',
        'is_active',
    ];

    protected $casts = [
        'tmt_pangkat_terakhir' => 'date',
        'tmt_kgb_terakhir' => 'date',
        'is_active' => 'boolean',
    ];

    public function golongan(): BelongsTo
    {
        return $this->belongsTo(RefGolongan::class, 'golongan_id');
    }

    public function kenaikanPangkat(): HasMany
    {
        return $this->hasMany(KenaikanPangkat::class, 'pegawai_id');
    }

    public function gajiBerkala(): HasMany
    {
        return $this->hasMany(GajiBerkala::class, 'pegawai_id');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }

    // Perkiraan KP Berikutnya (aturan umum: 4 tahun dari TMT pangkat terakhir)
    public function getPerkiraanKpBerikutnyaAttribute(): ?Carbon
    {
        if (! $this->tmt_pangkat_terakhir) {
            return null;
        }

        return Carbon::parse($this->tmt_pangkat_terakhir)->addYears(4);
    }

    // Perkiraan KGB Berikutnya (aturan umum: 2 tahun dari TMT KGB terakhir)
    public function getPerkiraanKgbBerikutnyaAttribute(): ?Carbon
    {
        if (! $this->tmt_kgb_terakhir) {
            return null;
        }

        return Carbon::parse($this->tmt_kgb_terakhir)->addYears(2);
    }

    // Status apakah sudah masuk masa calon kenaikan pangkat (sudah >= 4 tahun atau dalam 6 bulan ke depan)
    public function getIsCalonKpAttribute(): bool
    {
        if (! $this->perkiraan_kp_berikutnya) {
            return false;
        }

        return $this->perkiraan_kp_berikutnya->lte(now()->addMonths(6));
    }

    // Status apakah KGB jatuh tempo dalam 3 bulan ke depan
    public function getIsJatuhTempoKgbAttribute(): bool
    {
        if (! $this->perkiraan_kgb_berikutnya) {
            return false;
        }

        return $this->perkiraan_kgb_berikutnya->lte(now()->addMonths(3));
    }

    public function scopeCalonKp(Builder $query): Builder
    {
        return $query->whereNotNull('tmt_pangkat_terakhir')
            ->whereDate('tmt_pangkat_terakhir', '<=', now()->subYears(4)->addMonths(6)->toDateString());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePns(Builder $query): Builder
    {
        return $query->where('status_kepegawaian', 'PNS');
    }

    public function scopePppk(Builder $query): Builder
    {
        return $query->where('status_kepegawaian', 'PPPK');
    }

    public function scopeHonorer(Builder $query): Builder
    {
        return $query->where('status_kepegawaian', 'Honorer');
    }
}
