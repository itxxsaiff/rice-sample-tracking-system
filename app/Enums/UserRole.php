<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Employee => 'Employee',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role) => [$role->value => $role->label()])->all();
    }
}
