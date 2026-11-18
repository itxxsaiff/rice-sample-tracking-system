<?php

namespace App\Enums;

/**
 * Buyer response to a dispatched sample. Each dispatch (one sample to one
 * buyer) has its own status, independent of other buyers.
 */
enum DispatchStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting response',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
