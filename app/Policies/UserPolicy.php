<?php
namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Hak akses model User (auto-discovery Laravel 10). */
class UserPolicy extends TuBasePolicy
{
    protected string $module = 'users';

    public function delete(User $user, Model $record): bool
    {
        return parent::delete($user, $record) && ! $user->is($record);
    }
}
