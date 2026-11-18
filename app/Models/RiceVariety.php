<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiceVariety extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Options for forms: active varieties plus the currently selected one (even if inactive). */
    public static function options(?int $include = null): array
    {
        return static::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($include, fn ($q) => $q->orWhere('id', $include)))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function hasHistory(): bool
    {
        return false;
    }
}
