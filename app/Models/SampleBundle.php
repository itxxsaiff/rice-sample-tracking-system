<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SampleBundle extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['bundle_number', 'bundle_date', 'notes'];

    protected function casts(): array
    {
        return ['bundle_date' => 'date'];
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class)->orderBy('id');
    }

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
            ->where('bundle_number', 'like', $like)
            ->orWhere('notes', 'like', $like)
            ->orWhereHas('samples', fn (Builder $s) => $s->where('sample_number', 'like', $like)
                ->orWhereHas('mill', fn (Builder $m) => $m->where('name', 'like', $like))));
    }

    public function hasHistory(): bool
    {
        return $this->samples()->exists();
    }
}
