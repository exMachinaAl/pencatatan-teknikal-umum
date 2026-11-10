<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Kredensial lokal/testing sengaja berada di seeder, bukan di .env.
     * Production selalu meminta password rahasia ketika admin pertama dibuat.
     */
    public const LOCAL_TEST_PASSWORD = 'password';

    private const ADMIN = [
        'name' => 'Administrator Sistem (Divisi IT)',
        'username' => 'admin',
        'email' => 'admin@tu.sekolah.sch.id',
        'nomor_telepon' => '081200000001',
        'jabatan' => 'Administrator Infrastruktur',
        'unit_kerja' => 'Divisi IT',
        'role' => User::ROLE_ADMIN,
        'is_active' => true,
    ];

    public function run(): void
    {
        $existing = User::withTrashed()
            ->where('username', self::ADMIN['username'])
            ->orWhere('email', self::ADMIN['email'])
            ->first();

        if ($existing) {
            $this->completeMissingIdentity($existing);

            return;
        }

        User::create([
            ...self::ADMIN,
            'password' => $this->initialAdminPassword(),
        ]);

        $this->command?->info('Akun administrator awal berhasil dibuat. Buat akun operasional lain melalui menu Kelola Pengguna.');
    }

    private function completeMissingIdentity(User $admin): void
    {
        foreach (['username', 'nomor_telepon', 'jabatan', 'unit_kerja'] as $field) {
            if (blank($admin->{$field}) && filled(self::ADMIN[$field] ?? null)) {
                $admin->{$field} = self::ADMIN[$field];
            }
        }

        if ($admin->isDirty()) {
            $admin->save();
        }
    }

    private function initialAdminPassword(): string
    {
        if (! app()->environment('production')) {
            return self::LOCAL_TEST_PASSWORD;
        }

        if (! $this->command) {
            throw new \RuntimeException(
                'Admin pertama belum ada. Jalankan UserSeeder secara interaktif untuk menentukan password awal: php artisan db:seed --class=UserSeeder --force'
            );
        }

        $password = (string) $this->command->secret('Password awal administrator (minimal 12 karakter)');
        $confirmation = (string) $this->command->secret('Ulangi password awal administrator');

        if ($password === '') {
            throw new \RuntimeException(
                'Password admin belum diberikan. Jalankan UserSeeder secara interaktif tanpa opsi --no-interaction.'
            );
        }

        if (mb_strlen($password) < 12) {
            throw new \RuntimeException('Password awal administrator wajib minimal 12 karakter.');
        }

        if ($password !== $confirmation) {
            throw new \RuntimeException('Konfirmasi password administrator tidak cocok.');
        }

        if ($password === self::LOCAL_TEST_PASSWORD) {
            throw new \RuntimeException('Password development tidak boleh digunakan pada production.');
        }

        return $password;
    }
}
