<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefKlasifikasiSurat extends Model
{
    use HasFactory;

    protected $table = 'ref_klasifikasi_surat';

    protected $fillable = [
        'kode',
        'nama',
        'retensi_tahun',
    ];

    public function suratMasuk(): HasMany
    {
        return $this->hasMany(SuratMasuk::class, 'klasifikasi_id');
    }

    public function suratKeluar(): HasMany
    {
        return $this->hasMany(SuratKeluar::class, 'klasifikasi_id');
    }

    public function getLabelAttribute(): string
    {
        return "{$this->kode} - {$this->nama}";
    }
}
