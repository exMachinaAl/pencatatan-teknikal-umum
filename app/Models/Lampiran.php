<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Lampiran extends Model
{
    use HasFactory;

    protected $table = 'lampiran';

    protected $fillable = [
        'entitas',
        'entitas_id',
        'nama_file',
        'path',
        'mime',
        'ukuran_byte',
        'checksum',
        'uploaded_by',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUkuranFormattedAttribute(): string
    {
        $bytes = (int) $this->ukuran_byte;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
