<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
