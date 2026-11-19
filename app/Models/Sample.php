<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sample extends Model
{
    use HasFactory, RecordsActivity, Syncable;

    protected $fillable = [
        'sample_number', 'sample_bundle_id', 'mill_id', 'rice_variety_id',
        'collection_date', 'quantity', 'quantity_unit', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'collection_date' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    public function mill(): BelongsTo
    {
        return $this->belongsTo(Mill::class);
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(RiceVariety::class, 'rice_variety_id');
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(SampleBundle::class, 'sample_bundle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** City is derived from the mill rather than duplicated on the sample. */
    public function city(): ?string
    {
        return $this->mill?->city;
    }

    public function quantityLabel(): ?string
    {
        if ($this->quantity === null) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $this->quantity, 2), '0'), '.').' '.$this->quantity_unit;
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(SampleDispatch::class);
    }

    /** Dispatched samples are part of buyer history and cannot be deleted. */
    public function hasHistory(): bool
    {
        return $this->dispatches()->exists();
    }

    public function scopeFilter(Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['q'] ?? ''));

        $query
            ->when($term !== '', function (Builder $q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(fn (Builder $w) => $w
                    ->where('sample_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('mill', fn (Builder $m) => $m->where('name', 'like', $like)->orWhere('city', 'like', $like))
                    ->orWhereHas('variety', fn (Builder $v) => $v->where('name', 'like', $like))
                    ->orWhereHas('bundle', fn (Builder $b) => $b->where('bundle_number', 'like', $like)));
            })
            ->when($filters['mill_id'] ?? null, fn (Builder $q, $id) => $q->where('mill_id', $id))
            ->when($filters['variety_id'] ?? null, fn (Builder $q, $id) => $q->where('rice_variety_id', $id))
            ->when($filters['bundle_id'] ?? null, fn (Builder $q, $id) => $q->where('sample_bundle_id', $id))
            ->when($filters['city'] ?? null, fn (Builder $q, $city) => $q->whereHas('mill', fn (Builder $m) => $m->where('city', $city)))
            ->when($filters['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('collection_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('collection_date', '<=', $date))
            ->when(($filters['sent'] ?? null) === 'no', fn (Builder $q) => $q->doesntHave('dispatches'))
            ->when(($filters['sent'] ?? null) === 'yes', fn (Builder $q) => $q->has('dispatches'));
    }
}
