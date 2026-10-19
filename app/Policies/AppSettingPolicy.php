<?php
namespace App\Policies;

/** Hak akses model AppSetting (auto-discovery Laravel 10). */
class AppSettingPolicy extends TuBasePolicy
{
    protected string $module = 'settings';
}
