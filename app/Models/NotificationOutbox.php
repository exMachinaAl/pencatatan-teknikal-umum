<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationOutbox extends Model
{
    protected $table = 'notification_outbox';

    protected $fillable = [
        'entitas', 'entitas_id', 'kanal', 'tujuan', 'template', 'payload',
        'status', 'percobaan', 'error_terakhir', 'dikirim_pada',
    ];

    protected $casts = [
        'payload' => 'array',
        'percobaan' => 'integer',
        'dikirim_pada' => 'datetime',
    ];

    public static function destinationIsValid(string $channel, string $destination): bool
    {
        if ($destination === '' || mb_strlen($destination, 'UTF-8') > 190 || preg_match('/[\r\n\x00-\x1F\x7F]/', $destination)) {
            return false;
        }

        return match ($channel) {
            'email' => filter_var($destination, FILTER_VALIDATE_EMAIL) !== false,
            'whatsapp' => preg_match('/^\+?[0-9][0-9 .()-]{5,29}$/D', $destination) === 1,
            default => false,
        };
    }
}
