<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Disposisi extends Model
{
    use HasFactory;

    protected $table = 'disposisi';

    protected $fillable = [
        'surat_masuk_id',
        'dari_user_id',
        'kepada',
        'penanggung_jawab_id',
        'instruksi',
        'prioritas',
        'batas_waktu',
        'status',
        'selesai_pada',
        'catatan_tindak_lanjut',
    ];

    protected $casts = [
        'batas_waktu' => 'date',
        'selesai_pada' => 'datetime',
    ];

    public const STATUSES = [
        'terbuka' => 'Terbuka / Menunggu',
        'diproses' => 'Sedang Diproses',
        'selesai' => 'Selesai',
    ];

    protected static function booted(): void
    {
        static::creating(function (Disposisi $disposisi): void {
            $disposisi->dari_user_id ??= Auth::id();
        });

        static::saving(function (Disposisi $disposisi): void {
            $disposisi->status ??= 'terbuka';
            $disposisi->prioritas ??= 'normal';

            if (! array_key_exists((string) $disposisi->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => 'Status disposisi tidak valid.']);
            }

            if (! in_array($disposisi->prioritas, ['normal', 'penting', 'mendesak'], true)) {
                throw ValidationException::withMessages(['prioritas' => 'Prioritas disposisi tidak valid.']);
            }

            if ($disposisi->status === 'selesai') {
                $disposisi->selesai_pada ??= now();
            } else {
                $disposisi->selesai_pada = null;
            }
        });
    }

    public function suratMasuk(): BelongsTo
    {
        return $this->belongsTo(SuratMasuk::class, 'surat_masuk_id');
    }

    public function dariUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dari_user_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanggung_jawab_id');
    }

    public function scopeTerlambat($query)
    {
        return $query->whereNotNull('batas_waktu')
            ->whereDate('batas_waktu', '<', today())
            ->where('status', '!=', 'selesai');
    }

    public function getIsTerlambatAttribute(): bool
    {
        return $this->status !== 'selesai'
            && $this->batas_waktu !== null
            && $this->batas_waktu->isBefore(today());
    }
}
