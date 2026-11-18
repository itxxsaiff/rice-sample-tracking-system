<?php

namespace App\Policies;

use App\Models\SampleDispatch;
use App\Models\User;

class SampleDispatchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SampleDispatch $dispatch): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Record or correct the buyer's response. */
    public function respond(User $user, SampleDispatch $dispatch): bool
    {
        return true;
    }

    public function reopen(User $user, SampleDispatch $dispatch): bool
    {
        return ! $dispatch->isPending() && ! $dispatch->hasLoadings();
    }

    /** Only a still-pending dispatch (e.g. created by mistake) can be removed, and only by an admin. */
    public function delete(User $user, SampleDispatch $dispatch): bool
    {
        return $user->isAdmin() && $dispatch->isPending();
    }
}
