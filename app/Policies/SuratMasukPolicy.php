<?php
namespace App\Policies;

/** Hak akses model SuratMasuk (auto-discovery Laravel 10). */
class SuratMasukPolicy extends TuBasePolicy
{
    protected string $module = 'surat';
}
