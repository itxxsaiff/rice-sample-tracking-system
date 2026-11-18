<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /** Admins cannot change their own role (prevents locking everyone out of admin). */
    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isAdmin() && ! $actor->is($target);
    }

    /** Admins cannot deactivate themselves. */
    public function toggleActive(User $actor, User $target): bool
    {
        return $actor->isAdmin() && ! $actor->is($target);
    }

    /** Accounts are never hard-deleted; they are deactivated to keep history intact. */
    public function delete(User $actor, User $target): bool
    {
        return false;
    }
}
