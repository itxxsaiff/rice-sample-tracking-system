<?php

namespace App\Policies;

use App\Models\Mill;
use App\Models\User;

/**
 * Operational records: every active user (admin or employee) can view,
 * add and edit. Deleting is admin-only and only for records with no history.
 */
class MillPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Mill $mill): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Mill $mill): bool
    {
        return true;
    }

    public function delete(User $user, Mill $mill): bool
    {
        return $user->isAdmin() && ! $mill->hasHistory();
    }
}
