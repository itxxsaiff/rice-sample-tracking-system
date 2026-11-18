<?php

namespace App\Support;

use App\Models\Buyer;
use App\Models\Mill;

class Cities
{
    /** Configured suggestions merged with cities already used on mills and buyers. */
    public static function suggestions(): array
    {
        return collect(config('rice.cities'))
            ->merge(Mill::query()->distinct()->pluck('city'))
            ->merge(Buyer::query()->distinct()->pluck('city'))
            ->map(fn ($city) => trim($city))
            ->unique(fn ($city) => mb_strtolower($city))
            ->sort()->values()->all();
    }
}
