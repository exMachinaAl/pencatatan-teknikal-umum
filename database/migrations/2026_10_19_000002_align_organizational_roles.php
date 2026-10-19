<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'admin@tu.sekolah.sch.id')
            ->where('role', 'admin')
            ->whereIn('name', ['Operator TU (Admin)', 'Admin Tata Usaha'])
            ->update([
                'name' => 'Administrator Sistem (Divisi IT)',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'admin@tu.sekolah.sch.id')
            ->where('role', 'admin')
            ->where('name', 'Administrator Sistem (Divisi IT)')
            ->update([
                'name' => 'Admin Tata Usaha',
                'updated_at' => now(),
            ]);
    }
};
