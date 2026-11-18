<?php

namespace App\Enums;

/**
 * Loading progress of a confirmed dispatch (an order).
 *   pending     – confirmed, no truck recorded yet
 *   in_progress – one or more trucks recorded, order still open
 *   completed   – the order has been fully loaded and closed
 */
enum LoadingStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Loading pending',
            self::InProgress => 'Loading in progress',
            self::Completed => 'Completed',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
