<?php
namespace App\Policies;

/** Hak akses model AuditLog (auto-discovery Laravel 10). */
class AuditLogPolicy extends TuBasePolicy
{
    protected string $module = 'audit';
}
