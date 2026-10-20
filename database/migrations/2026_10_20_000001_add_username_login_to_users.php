<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->after('id');
            $table->string('email')->nullable()->change();
        });

        $preferredUsernames = [
            'admin@tu.sekolah.sch.id' => 'admin',
            'kepala.tu@tu.sekolah.sch.id' => 'kepala_tu',
            'kepala@tu.sekolah.sch.id' => 'kepala_sekolah',
            'staf@tu.sekolah.sch.id' => 'staf_tu',
            'pegawai@tu.sekolah.sch.id' => 'pegawai',
        ];
        $used = [];

        DB::table('users')->orderBy('id')->get(['id', 'name', 'email'])->each(
            function (object $user) use (&$used, $preferredUsernames): void {
                $email = mb_strtolower(trim((string) $user->email));
                $source = $preferredUsernames[$email]
                    ?? Str::before($email, '@')
                    ?: Str::slug((string) $user->name, '_');
                $base = Str::limit(
                    preg_replace('/[^a-z0-9._-]+/', '_', mb_strtolower($source)) ?: 'user',
                    40,
                    ''
                );
                $base = trim($base, '._-');
                if (strlen($base) < 3) {
                    $base = 'user_'.$user->id;
                }

                $username = $base;
                $suffix = 1;
                while (isset($used[$username])) {
                    $username = Str::limit($base, 44, '').'_'.++$suffix;
                }

                $used[$username] = true;
                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            }
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNull('email')->orderBy('id')->get(['id'])->each(
            fn (object $user) => DB::table('users')->where('id', $user->id)->update([
                'email' => 'user-'.$user->id.'@local.invalid',
            ])
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
