<?php

namespace App\Policies;

use App\Models\RiceVariety;
use App\Models\User;

class RiceVarietyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RiceVariety $variety): bool
    {
        return true;
    }

    public function delete(User $user, RiceVariety $variety): bool
    {
        return $user->isAdmin() && ! $variety->hasHistory();
    }
}
