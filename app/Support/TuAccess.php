<?php
namespace App\Support;

use App\Models\GajiBerkala;
use App\Models\KenaikanPangkat;
use App\Models\Legalisir;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Matriks hak akses TU untuk CRUD standar melalui Laravel Policy.
 * Aksi custom pada Filament Resource lama perlu pemeriksaan ->authorize() tersendiri.
 */
final class TuAccess
{
    public const LABELS = [
        'surat' => 'Surat & Kearsipan',
        'legalisir' => 'Legalisir',
        'kepegawaian' => 'Administrasi Kepegawaian',
        'settings' => 'Pengaturan Aplikasi',
        'users' => 'Akun Pengguna',
        'backup' => 'Backup Database',
        'audit' => 'Audit Log',
    ];

    private const ACTION_LABELS = [
        'view' => 'lihat',
        'create' => 'buat',
        'update' => 'ubah',
        'delete' => 'hapus',
        'transition' => 'ubah status',
        'print' => 'cetak',
        'export' => 'ekspor',
        'import' => 'impor',
        'manage' => 'kelola',
    ];

    public static function allows(?User $user, string $module, string $ability): bool
    {
        return $user?->canPerform($module, $ability === 'list' ? 'view' : $ability) ?? false;
    }

    public static function summary(string $role, string $module): string
    {
        $abilities = User::permissionsForRole($role, $module);

        if ($abilities === []) {
            return 'Tidak ada';
        }

        return implode(', ', array_map(
            fn (string $ability): string => self::ACTION_LABELS[$ability] ?? $ability,
            $abilities,
        ));
    }

    public static function roles(): array
    {
        return User::ROLE_LABELS;
    }

    public static function recordIsLocked(Model $record): bool
    {
        $status = $record->getAttribute('status');

        return match (true) {
            $record instanceof SuratMasuk => $status === 'diarsipkan',
            $record instanceof SuratKeluar => in_array($status, ['terkirim', 'diarsipkan'], true),
            $record instanceof Legalisir => in_array($status, ['diambil', 'ditolak'], true),
            $record instanceof KenaikanPangkat, $record instanceof GajiBerkala => $status === 'diserahkan',
            default => false,
        };
    }
}
