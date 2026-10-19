<?php
namespace App\Policies;

/** Hak akses model KenaikanPangkat (auto-discovery Laravel 10). */
class KenaikanPangkatPolicy extends TuBasePolicy
{
    protected string $module = 'kepegawaian';
}
