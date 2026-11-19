<?php

namespace App\Services;

use App\Models\Buyer;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\User;

/**
 * Create/update for directory records (mills, buyers, varieties).
 * Shared by the online controllers and the offline sync endpoint.
 */
class DirectoryService
{
    public function createMill(array $data, User $user, ?string $uuid = null): Mill
    {
        $mill = new Mill($data);
        $mill->uuid = $uuid ?? $mill->uuid;
        $mill->created_by = $user->id;
        $mill->save();
        $mill->recordActivity('created', "Added rice mill {$mill->label()}");

        return $mill;
    }

    public function updateMill(Mill $mill, array $data): Mill
    {
        $mill->update($data);

        return $mill;
    }

    public function createBuyer(array $data, User $user, ?string $uuid = null): Buyer
    {
        $buyer = new Buyer($data);
        $buyer->uuid = $uuid ?? $buyer->uuid;
        $buyer->created_by = $user->id;
        $buyer->save();
        $buyer->recordActivity('created', "Added buyer {$buyer->label()}");

        return $buyer;
    }

    public function updateBuyer(Buyer $buyer, array $data): Buyer
    {
        $buyer->update($data);

        return $buyer;
    }

    public function createVariety(array $data, ?string $uuid = null): RiceVariety
    {
        $variety = new RiceVariety(collect($data)->only(['name', 'description'])->all());
        $variety->uuid = $uuid ?? $variety->uuid;
        $variety->save();

        return $variety;
    }
}
