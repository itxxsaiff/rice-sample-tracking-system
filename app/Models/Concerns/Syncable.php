<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Stable UUID + optimistic-lock version for records the offline app can
 * create or edit. Online code paths need no changes: the uuid is assigned
 * automatically and the version bumps on every real update.
 */
trait Syncable
{
    public static function bootSyncable(): void
    {
        static::creating(function ($model) {
            $model->uuid ??= (string) Str::uuid();
        });

        static::updating(function ($model) {
            if ($model->hasVersionColumn()) {
                $model->version = (int) $model->getOriginal('version', 1) + 1;
            }
        });
    }

    public function hasVersionColumn(): bool
    {
        return property_exists($this, 'versioned') ? (bool) $this->versioned : true;
    }

    public function scopeWhereUuid(Builder $query, string $uuid): void
    {
        $query->where($this->getTable().'.uuid', $uuid);
    }
}
