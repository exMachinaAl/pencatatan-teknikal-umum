<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefGolongan extends Model
{
    use HasFactory;

    protected $table = 'ref_golongan';

    protected $fillable = [
        'kode',
        'pangkat',
        'tingkat',
        'urutan',
    ];

    public function pegawai(): HasMany
    {
        return $this->hasMany(Pegawai::class, 'golongan_id');
    }

    public function getNamaLengkapAttribute(): string
    {
        return "{$this->pangkat} ({$this->kode})";
    }
}
