<?php
namespace App\Policies;

/** Hak akses model Pegawai (auto-discovery Laravel 10). */
class PegawaiPolicy extends TuBasePolicy
{
    protected string $module = 'kepegawaian';
}
