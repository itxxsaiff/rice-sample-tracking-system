<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait RecordsActivity
{
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('id');
    }

    public function recordActivity(string $event, string $description, array $properties = []): Activity
    {
        return Activity::record($this, $event, $description, $properties);
    }
}
