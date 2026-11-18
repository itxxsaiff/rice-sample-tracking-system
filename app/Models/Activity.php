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

    /** Link to the record this activity is about (null if it no longer exists). */
    public function subjectUrl(): ?string
    {
        if (! $this->subject) {
            return null;
        }

        return match ($this->subject_type) {
            'mill' => route('mills.show', $this->subject_id),
            'buyer' => route('buyers.show', $this->subject_id),
            'sample' => route('samples.show', $this->subject_id),
            'bundle' => route('bundles.show', $this->subject_id),
            'dispatch' => route('dispatches.show', $this->subject_id),
            'loading' => route('loadings.show', $this->subject_id),
            default => null,
        };
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
