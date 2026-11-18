<?php

namespace App\Policies;

use App\Models\Buyer;
use App\Models\User;

/**
 * Operational records: every active user (admin or employee) can view,
 * add and edit. Deleting is admin-only and only for records with no history.
 */
class BuyerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Buyer $buyer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Buyer $buyer): bool
    {
        return true;
    }

    public function delete(User $user, Buyer $buyer): bool
    {
        return $user->isAdmin() && ! $buyer->hasHistory();
    }
}
