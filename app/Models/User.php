<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // Pengguna aktif dapat mengakses sistem sesuai peran
        return (bool) $this->is_active;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isKepala(): bool
    {
        return $this->role === 'kepala';
    }

    public function isStaf(): bool
    {
        return $this->role === 'staf';
    }

    public function isPegawai(): bool
    {
        return $this->role === 'pegawai';
    }

    public function canViewSalary(): bool
    {
        // Hak akses data gaji: hanya admin dan kepala (UU PDP)
        return in_array($this->role, ['admin', 'kepala']);
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }
}
