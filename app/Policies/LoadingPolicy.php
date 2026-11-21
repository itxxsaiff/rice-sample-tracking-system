<?php

namespace App\Policies;

use App\Models\Loading;
use App\Models\User;

class LoadingPolicy
{
    public function view(User $user, Loading $loading): bool
    {
        return true;
    }

    /** Open trucks can be edited by anyone; finished records only by an admin. */
    public function update(User $user, Loading $loading): bool
    {
        return $user->isAdmin() || (! $loading->isCompleted() && ! $loading->dispatch->isLoadingCompleted());
    }

    public function markLoaded(User $user, Loading $loading): bool
    {
        return ! $loading->isCompleted();
    }

    /**
     * Whoever recorded the truck can always add its photos: an order now completes by
     * itself on its last truck, and photos taken offline upload just after the truck.
     */
    public function addPhotos(User $user, Loading $loading): bool
    {
        return $user->isAdmin() || $loading->created_by === $user->id || ! $loading->dispatch->isLoadingCompleted();
    }

    public function deletePhoto(User $user, Loading $loading): bool
    {
        return $user->isAdmin() || ! $loading->isCompleted();
    }

    /** Only a truck that is still being loaded can be removed, by an admin. */
    public function delete(User $user, Loading $loading): bool
    {
        return $user->isAdmin() && ! $loading->isCompleted();
    }
}
