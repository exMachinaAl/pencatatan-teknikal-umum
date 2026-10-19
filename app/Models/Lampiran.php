<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lampiran extends Model
{
    use HasFactory;

    protected $table = 'lampiran';

    protected $fillable = [
        'entitas',
        'entitas_id',
        'nama_file',
        'path',
        'versi',
        'is_current',
        'akses',
        'keterangan',
        'mime',
        'ukuran_byte',
        'checksum',
        'uploaded_by',
    ];

    protected $casts = [
        'versi' => 'integer',
        'is_current' => 'boolean',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(LampiranAccessLog::class, 'lampiran_id');
    }

    public function getUkuranFormattedAttribute(): string
    {
        $bytes = (int) $this->ukuran_byte;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    public function getPreviewMimeAttribute(): ?string
    {
        $mime = strtolower((string) $this->mime);
        if (in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            return $mime;
        }

        return match (strtolower(pathinfo($this->path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }

    public function getIsPreviewableAttribute(): bool
    {
        return $this->preview_mime !== null;
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->preview_mime, 'image/');
    }
}
