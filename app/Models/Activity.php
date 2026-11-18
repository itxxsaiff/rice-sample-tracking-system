<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    /** Record a business event against a model, attributed to the current user. */
    public static function record(Model $subject, string $event, string $description, array $properties = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'System']);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Bootstrap icon + tone for the activity feed. */
    public function icon(): array
    {
        return match ($this->event) {
            'created' => ['plus-circle', 'green'],
            'updated' => ['pencil', 'slate'],
            'dispatched' => ['send', 'blue'],
            'confirmed' => ['check-circle', 'green'],
            'rejected' => ['x-circle', 'red'],
            'reopened' => ['arrow-counterclockwise', 'gold'],
            'loading_recorded' => ['truck', 'teal'],
            'loading_completed', 'order_completed' => ['check2-all', 'teal'],
            'photos_added' => ['camera', 'purple'],
            default => ['dot', 'slate'],
        };
    }
}
