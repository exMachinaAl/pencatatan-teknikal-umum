<?php

namespace App\Models;

use App\Models\Concerns\HasOptimisticLock;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasApiTokens, HasFactory, HasOptimisticLock, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_KEPALA_TU = 'kepala_tu';

    public const ROLE_PETUGAS_TU = 'petugas_tu';

    public const ROLE_KEPEGAWAIAN = 'kepegawaian';

    public const ROLE_KEPALA_SEKOLAH = 'kepala_sekolah';

    public const ROLE_AUDITOR = 'auditor';

    public const ROLE_PEGAWAI = 'pegawai';

    public const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Administrator Sistem (Fasilitator TI)',
        self::ROLE_KEPALA_TU => 'Kepala Tata Usaha (Pimpinan TU)',
        self::ROLE_PETUGAS_TU => 'Petugas TU (Persuratan & Legalisir)',
        self::ROLE_KEPEGAWAIAN => 'Petugas Kepegawaian',
        self::ROLE_KEPALA_SEKOLAH => 'Kepala Sekolah',
        self::ROLE_AUDITOR => 'Auditor / Pengawas',
        self::ROLE_PEGAWAI => 'Pegawai / Pemohon',
    ];

    /**
     * Administrator Sistem berada di luar hierarki operasional TU. Peran ini
     * memfasilitasi akun dan infrastruktur, sedangkan Kepala TU memimpin TU.
     */
    private const ROLE_PERMISSIONS = [
        self::ROLE_ADMIN => [
            'users' => ['view', 'create', 'update', 'delete'],
            'settings' => ['view', 'create', 'update', 'delete'],
            'backup' => ['view', 'manage'],
            'audit' => ['view'],
            'surat' => ['view'],
            'legalisir' => ['view'],
        ],
        self::ROLE_KEPALA_TU => [
            'surat' => ['view', 'create', 'update', 'transition', 'export', 'import'],
            'legalisir' => ['view', 'create', 'update', 'transition', 'print', 'export', 'import'],
            'kepegawaian' => ['view', 'create', 'update', 'transition', 'export', 'import'],
            'audit' => ['view'],
        ],
        self::ROLE_PETUGAS_TU => [
            'surat' => ['view', 'create', 'update', 'transition', 'export', 'import'],
            'legalisir' => ['view', 'create', 'update', 'transition', 'print', 'export', 'import'],
        ],
        self::ROLE_KEPEGAWAIAN => [
            'kepegawaian' => ['view', 'create', 'update', 'transition', 'export', 'import'],
        ],
        self::ROLE_KEPALA_SEKOLAH => [
            'surat' => ['view'],
            'legalisir' => ['view', 'print'],
            'kepegawaian' => ['view'],
        ],
        self::ROLE_AUDITOR => [
            'surat' => ['view'],
            'legalisir' => ['view'],
            'kepegawaian' => ['view'],
            'audit' => ['view'],
        ],
        self::ROLE_PEGAWAI => [],
    ];

    protected $fillable = [
        'pegawai_id',
        'username',
        'name',
        'email',
        'nomor_telepon',
        'jabatan',
        'unit_kerja',
        'foto_path',
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

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->username !== null) {
                $user->username = mb_strtolower(trim((string) $user->username)) ?: null;
            }

            if ($user->username !== null) {
                if (! preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/D', $user->username)) {
                    throw ValidationException::withMessages([
                        'username' => 'Username harus 3–50 karakter dan hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.',
                    ]);
                }

                if (preg_match('/^\d+$/D', $user->username)) {
                    throw ValidationException::withMessages([
                        'username' => 'Username tidak boleh seluruhnya berupa angka agar tidak bertabrakan dengan NIP.',
                    ]);
                }

                $usernameDipakai = static::withTrashed()
                    ->where('username', $user->username)
                    ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
                    ->exists();

                if ($usernameDipakai) {
                    throw ValidationException::withMessages(['username' => 'Username sudah digunakan.']);
                }

                $nipDipakai = Pegawai::withTrashed()
                    ->where('nip', $user->username)
                    ->when($user->pegawai_id, fn ($query) => $query->whereKeyNot($user->pegawai_id))
                    ->exists();

                if ($nipDipakai) {
                    throw ValidationException::withMessages([
                        'username' => 'Username bertabrakan dengan NIP pegawai lain.',
                    ]);
                }
            }

            if ($user->email !== null) {
                $user->email = mb_strtolower(trim((string) $user->email)) ?: null;
            }

            if ($user->email !== null && (mb_strlen($user->email, 'UTF-8') > 160 || ! filter_var($user->email, FILTER_VALIDATE_EMAIL))) {
                throw ValidationException::withMessages(['email' => 'Alamat email tidak valid.']);
            }

            if ($user->nomor_telepon !== null) {
                $user->nomor_telepon = trim((string) $user->nomor_telepon) ?: null;
            }

            if ($user->nomor_telepon !== null && ! preg_match('/^[0-9+().\-\s]{6,30}$/D', $user->nomor_telepon)) {
                throw ValidationException::withMessages(['nomor_telepon' => 'Nomor telepon tidak valid.']);
            }

            if ($user->pegawai_id) {
                $sudahTerhubung = static::withTrashed()
                    ->where('pegawai_id', $user->pegawai_id)
                    ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
                    ->exists();

                if ($sudahTerhubung) {
                    throw ValidationException::withMessages(['pegawai_id' => 'Data pegawai sudah terhubung dengan akun lain.']);
                }
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active
            && array_key_exists($this->normalizedRole(), self::ROLE_LABELS);
    }

    public static function findForLogin(string $identifier): ?self
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $normalized = mb_strtolower($identifier);
        $nip = preg_replace('/\s+/', '', $identifier);

        $user = static::query()
            ->where('username', $normalized)
            ->orWhereRaw('LOWER(email) = ?', [$normalized])
            ->first();

        return $user ?? static::query()
            ->whereHas('pegawai', fn ($pegawai) => $pegawai->where('nip', $nip))
            ->first();
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->foto_path ? route('profil.pengguna.foto', $this) : null;
    }

    public function isAdmin(): bool
    {
        return $this->normalizedRole() === self::ROLE_ADMIN;
    }

    public function isKepala(): bool
    {
        return in_array($this->normalizedRole(), [self::ROLE_KEPALA_TU, self::ROLE_KEPALA_SEKOLAH], true);
    }

    public function isStaf(): bool
    {
        return in_array($this->normalizedRole(), [self::ROLE_PETUGAS_TU, self::ROLE_KEPEGAWAIAN], true);
    }

    public function isPegawai(): bool
    {
        return $this->normalizedRole() === self::ROLE_PEGAWAI;
    }

    public function canViewSalary(): bool
    {
        return in_array($this->normalizedRole(), [self::ROLE_KEPALA_TU, self::ROLE_KEPEGAWAIAN], true);
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function normalizedRole(): string
    {
        // Alias dipertahankan selama deployment bergulir sebelum migrasi dijalankan.
        return self::normalizeRole((string) $this->role);
    }

    public static function normalizeRole(string $role): string
    {
        return match ($role) {
            'staf' => self::ROLE_PETUGAS_TU,
            'kepala' => self::ROLE_KEPALA_SEKOLAH,
            default => $role,
        };
    }

    public static function permissionsForRole(string $role, string $module): array
    {
        return self::ROLE_PERMISSIONS[self::normalizeRole($role)][$module] ?? [];
    }

    public static function roleCanPerform(string $role, string $module, string $action): bool
    {
        return in_array($action, self::permissionsForRole($role, $module), true);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->normalizedRole()] ?? 'Role tidak dikenali';
    }

    public function canPerform(string $module, string $action): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return self::roleCanPerform($this->normalizedRole(), $module, $action);
    }
}
