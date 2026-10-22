<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gaji_berkala', function (Blueprint $table): void {
            $table->date('tmt')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gaji_berkala', function (Blueprint $table): void {
            $table->date('tmt')->nullable(false)->change();
        });
    }
};
