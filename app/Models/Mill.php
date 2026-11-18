<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mill extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['name', 'city', 'contact_person', 'phone', 'address', 'notes'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('city', 'like', $like)
            ->orWhere('contact_person', 'like', $like)
            ->orWhere('phone', 'like', $like));
    }

    /** True once the mill has business records attached (blocks deletion). */
    public function hasHistory(): bool
    {
        return false;
    }

    public function label(): string
    {
        return "{$this->name}, {$this->city}";
    }

    /** Distinct cities already used, merged with configured suggestions. */
    public static function citySuggestions(): array
    {
        return collect(config('rice.cities'))
            ->merge(static::query()->distinct()->pluck('city'))
            ->map(fn ($c) => trim($c))->unique(fn ($c) => mb_strtolower($c))->sort()->values()->all();
    }
}
