<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_hanya_membuat_satu_administrator_awal(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'username' => 'admin',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->assertTrue(Hash::check(
            UserSeeder::LOCAL_TEST_PASSWORD,
            User::where('username', 'admin')->firstOrFail()->password,
        ));
    }

    public function test_seeder_melengkapi_identitas_tanpa_menimpa_password_role_atau_status_akun_lama(): void
    {
        $existing = User::create([
            'name' => 'Admin Existing',
            'email' => 'admin@tu.sekolah.sch.id',
            'password' => 'rahasia-yang-sudah-diganti',
            'role' => User::ROLE_AUDITOR,
            'is_active' => false,
        ]);

        $this->seed(UserSeeder::class);

        $existing->refresh();
        $this->assertTrue(Hash::check('rahasia-yang-sudah-diganti', $existing->password));
        $this->assertSame(User::ROLE_AUDITOR, $existing->role);
        $this->assertFalse($existing->is_active);
        $this->assertSame('admin', $existing->username);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_menjalankan_seeder_ulang_tidak_mereset_password_admin(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('username', 'admin')->firstOrFail();
        $admin->update(['password' => 'password-baru-yang-aman']);

        $this->seed(UserSeeder::class);

        $this->assertTrue(Hash::check('password-baru-yang-aman', $admin->refresh()->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_akun_seluruh_role_hanya_dibuat_oleh_seeder_demo_eksplisit(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $this->assertDatabaseCount('users', 7);

        foreach (['kepala_tu', 'kepala_sekolah', 'staf_tu', 'kepegawaian', 'auditor', 'pegawai'] as $username) {
            $user = User::where('username', $username)->firstOrFail();
            $this->assertTrue(Hash::check(DemoUserSeeder::DEMO_PASSWORD, $user->password));
        }
    }

    public function test_production_tidak_memakai_password_fallback_tanpa_bootstrap_interaktif(): void
    {
        $app = app();
        $app->detectEnvironment(fn (): string => 'production');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Jalankan UserSeeder secara interaktif');

            (new UserSeeder)->setContainer($app)->run();
        } finally {
            $app->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_bootstrap_production_meminta_dan_menyimpan_password_rahasia(): void
    {
        $password = 'Admin-Awal-Yang-Aman-2026';
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('secret')->twice()->andReturn($password, $password);
        $command->shouldReceive('info')->once();

        $app = app();
        $app->detectEnvironment(fn (): string => 'production');

        try {
            (new UserSeeder)
                ->setContainer($app)
                ->setCommand($command)
                ->run();
        } finally {
            $app->detectEnvironment(fn (): string => 'testing');
        }

        $admin = User::where('username', 'admin')->sole();
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue(Hash::check($password, $admin->password));
    }
}
