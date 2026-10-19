<?php
namespace App\Policies;

use App\Models\User;
use App\Support\TuAccess;
use Illuminate\Database\Eloquent\Model;

/** Dasar policy untuk CRUD Filament standar dan Gate Laravel. */
abstract class TuBasePolicy
{
    protected string $module;

    public function viewAny(User $user): bool { return $user->canPerform($this->module, 'view'); }
    public function view(User $user, Model $record): bool { return $user->canPerform($this->module, 'view'); }
    public function create(User $user): bool { return $user->canPerform($this->module, 'create'); }
    public function update(User $user, Model $record): bool
    {
        return $user->canPerform($this->module, 'update') && ! TuAccess::recordIsLocked($record);
    }
    public function delete(User $user, Model $record): bool { return $user->canPerform($this->module, 'delete'); }
    public function deleteAny(User $user): bool { return $user->canPerform($this->module, 'delete'); }
    public function restore(User $user, Model $record): bool { return $user->canPerform($this->module, 'restore'); }
    public function restoreAny(User $user): bool { return $user->canPerform($this->module, 'restore'); }
    public function forceDelete(User $user, Model $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function replicate(User $user, Model $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}
