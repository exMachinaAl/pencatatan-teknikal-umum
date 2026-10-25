<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'audit_log';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_username',
        'user_role',
        'aksi',
        'entitas',
        'entitas_id',
        'data_sebelum',
        'data_sesudah',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'data_sebelum' => 'array',
        'data_sesudah' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(
        string $aksi,
        ?string $entitas = null,
        ?int $entitasId = null,
        ?array $before = null,
        ?array $after = null
    ): static {
        $user = Auth::user();

        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistem / Anonim',
            'user_username' => $user?->username,
            'user_role' => $user?->normalizedRole(),
            'aksi' => $aksi,
            'entitas' => $entitas,
            'entitas_id' => $entitasId,
            'data_sebelum' => $before,
            'data_sesudah' => $after,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
