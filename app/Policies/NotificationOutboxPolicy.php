<?php

namespace App\Policies;

use App\Models\NotificationOutbox;
use App\Models\User;

class NotificationOutboxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canPerform('legalisir', 'view');
    }

    public function view(User $user, NotificationOutbox $record): bool
    {
        return $user->canPerform('legalisir', 'view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NotificationOutbox $record): bool
    {
        return false;
    }

    public function delete(User $user, NotificationOutbox $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, NotificationOutbox $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, NotificationOutbox $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
