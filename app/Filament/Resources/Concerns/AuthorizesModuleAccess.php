<?php

namespace App\Filament\Resources\Concerns;

use App\Models\User;
use App\Support\TuAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuthorizesModuleAccess
{
    abstract protected static function accessModule(): string;

    protected static function resourcePermission(string $action): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canPerform(static::accessModule(), $action);
    }

    protected static function authorizeResourceAction(string $action): void
    {
        abort_unless(static::resourcePermission($action), 403);
    }

    public static function canViewAny(): bool
    {
        return static::resourcePermission('view');
    }

    public static function canView(Model $record): bool
    {
        return static::resourcePermission('view');
    }

    public static function canCreate(): bool
    {
        return static::resourcePermission('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::resourcePermission('update') && ! static::recordIsLocked($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::resourcePermission('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::resourcePermission('delete');
    }

    public static function canRestore(Model $record): bool
    {
        return static::resourcePermission('restore');
    }

    public static function canRestoreAny(): bool
    {
        return static::resourcePermission('restore');
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    protected static function recordIsLocked(Model $record): bool
    {
        return TuAccess::recordIsLocked($record);
    }
}
