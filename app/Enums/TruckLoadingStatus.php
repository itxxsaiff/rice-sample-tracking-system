<?php

namespace App\Enums;

/** Status of a single truck loading record. */
enum TruckLoadingStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Loading',
            self::Completed => 'Loaded',
        };
    }
}
