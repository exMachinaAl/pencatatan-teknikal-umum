<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            GolonganSeeder::class,
            KlasifikasiSuratSeeder::class,
            UserSeeder::class,
            AppSettingSeeder::class,
            SampleDataSeeder::class,
        ]);
    }
}
