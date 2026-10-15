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
        'instruksi',
        'batas_waktu',
        'status',
        'catatan_tindak_lanjut',
    ];

    protected $casts = [
        'batas_waktu' => 'date',
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
}
