<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LampiranAccessLog extends Model
{
    public $timestamps = false;

    protected $table = 'lampiran_access_log';

    protected $fillable = [
        'lampiran_id', 'user_id', 'aksi', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function lampiran(): BelongsTo
    {
        return $this->belongsTo(Lampiran::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
