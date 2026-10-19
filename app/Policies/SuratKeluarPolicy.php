<?php
namespace App\Policies;

/** Hak akses model SuratKeluar (auto-discovery Laravel 10). */
class SuratKeluarPolicy extends TuBasePolicy
{
    protected string $module = 'surat';
}
