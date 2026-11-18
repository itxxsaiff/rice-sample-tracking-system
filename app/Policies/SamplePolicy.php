<?php

namespace App\Policies;

use App\Models\Sample;
use App\Models\User;

/**
 * Operational records: every active user (admin or employee) can view,
 * add and edit. Deleting is admin-only and only for records with no history.
 */
class SamplePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sample $sample): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Sample $sample): bool
    {
        return true;
    }

    public function delete(User $user, Sample $sample): bool
    {
        return $user->isAdmin() && ! $sample->hasHistory();
    }
}
