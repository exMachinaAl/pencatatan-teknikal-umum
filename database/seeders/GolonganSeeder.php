<?php

namespace Database\Seeders;

use App\Models\RefGolongan;
use Illuminate\Database\Seeder;

class GolonganSeeder extends Seeder
{
    public function run(): void
    {
        $golongans = [
            // Golongan I
            ['kode' => 'I/a', 'pangkat' => 'Juru Muda', 'tingkat' => 'I', 'urutan' => 1],
            ['kode' => 'I/b', 'pangkat' => 'Juru Muda Tingkat I', 'tingkat' => 'I', 'urutan' => 2],
            ['kode' => 'I/c', 'pangkat' => 'Juru', 'tingkat' => 'I', 'urutan' => 3],
            ['kode' => 'I/d', 'pangkat' => 'Juru Tingkat I', 'tingkat' => 'I', 'urutan' => 4],

            // Golongan II
            ['kode' => 'II/a', 'pangkat' => 'Pengatur Muda', 'tingkat' => 'II', 'urutan' => 5],
            ['kode' => 'II/b', 'pangkat' => 'Pengatur Muda Tingkat I', 'tingkat' => 'II', 'urutan' => 6],
            ['kode' => 'II/c', 'pangkat' => 'Pengatur', 'tingkat' => 'II', 'urutan' => 7],
            ['kode' => 'II/d', 'pangkat' => 'Pengatur Tingkat I', 'tingkat' => 'II', 'urutan' => 8],

            // Golongan III
            ['kode' => 'III/a', 'pangkat' => 'Penata Muda', 'tingkat' => 'III', 'urutan' => 9],
            ['kode' => 'III/b', 'pangkat' => 'Penata Muda Tingkat I', 'tingkat' => 'III', 'urutan' => 10],
            ['kode' => 'III/c', 'pangkat' => 'Penata', 'tingkat' => 'III', 'urutan' => 11],
            ['kode' => 'III/d', 'pangkat' => 'Penata Tingkat I', 'tingkat' => 'III', 'urutan' => 12],

            // Golongan IV
            ['kode' => 'IV/a', 'pangkat' => 'Pembina', 'tingkat' => 'IV', 'urutan' => 13],
            ['kode' => 'IV/b', 'pangkat' => 'Pembina Tingkat I', 'tingkat' => 'IV', 'urutan' => 14],
            ['kode' => 'IV/c', 'pangkat' => 'Pembina Utama Muda', 'tingkat' => 'IV', 'urutan' => 15],
            ['kode' => 'IV/d', 'pangkat' => 'Pembina Utama Madya', 'tingkat' => 'IV', 'urutan' => 16],
            ['kode' => 'IV/e', 'pangkat' => 'Pembina Utama', 'tingkat' => 'IV', 'urutan' => 17],

            // PPPK Golongan
            ['kode' => 'IX (PPPK)', 'pangkat' => 'Ahli Pertama (S1)', 'tingkat' => 'PPPK', 'urutan' => 18],
            ['kode' => 'X (PPPK)', 'pangkat' => 'Ahli Muda (S2)', 'tingkat' => 'PPPK', 'urutan' => 19],
            ['kode' => 'XI (PPPK)', 'pangkat' => 'Ahli Madya', 'tingkat' => 'PPPK', 'urutan' => 20],
        ];

        foreach ($golongans as $gol) {
            RefGolongan::firstOrCreate(['kode' => $gol['kode']], $gol);
        }
    }
}
