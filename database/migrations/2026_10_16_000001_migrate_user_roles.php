<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'staf')->update(['role' => 'petugas_tu']);
        DB::table('users')->where('role', 'kepala')->update(['role' => 'kepala_sekolah']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'petugas_tu')->update(['role' => 'staf']);
        DB::table('users')->where('role', 'kepala_sekolah')->update(['role' => 'kepala']);
        DB::table('users')->whereIn('role', ['kepala_tu', 'kepegawaian', 'auditor'])->update(['role' => 'staf']);
    }
};
