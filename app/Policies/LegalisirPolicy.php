<?php
namespace App\Policies;

/** Hak akses model Legalisir (auto-discovery Laravel 10). */
class LegalisirPolicy extends TuBasePolicy
{
    protected string $module = 'legalisir';
}
