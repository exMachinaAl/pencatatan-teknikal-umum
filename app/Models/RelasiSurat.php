<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelasiSurat extends Model
{
    protected $table = 'relasi_surat';

    protected $fillable = [
        'surat_masuk_id', 'surat_keluar_id', 'jenis_relasi', 'catatan', 'created_by',
    ];

    public function suratMasuk(): BelongsTo
    {
        return $this->belongsTo(SuratMasuk::class);
    }

    public function suratKeluar(): BelongsTo
    {
        return $this->belongsTo(SuratKeluar::class);
    }
}
