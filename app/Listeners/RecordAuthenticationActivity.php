<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordAuthenticationActivity
{
    public function handle(Login|Logout|Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $action = match (true) {
            $event instanceof Login => 'login_berhasil',
            $event instanceof Logout => 'logout',
            default => 'login_gagal',
        };

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistem / Anonim',
            'user_username' => $user?->username,
            'user_role' => $user?->normalizedRole(),
            'aksi' => $action,
            'entitas' => 'Autentikasi',
            'entitas_id' => $user?->id,
            'data_sesudah' => ['hasil' => $action],
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
