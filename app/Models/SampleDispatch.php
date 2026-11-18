<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleDispatch extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['sample_id', 'buyer_id', 'dispatch_date', 'notes'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date',
            'confirmation_date' => 'date',
            'rejection_date' => 'date',
            'responded_at' => 'datetime',
            'loading_completed_at' => 'datetime',
            'confirmed_quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'status' => DispatchStatus::class,
            'loading_status' => LoadingStatus::class,
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function isPending(): bool
    {
        return $this->status === DispatchStatus::Pending;
    }

    public function isConfirmed(): bool
    {
        return $this->status === DispatchStatus::Confirmed;
    }

    public function isRejected(): bool
    {
        return $this->status === DispatchStatus::Rejected;
    }

    public function hasHistory(): bool
    {
        return ! $this->isPending();
    }

    /** Whether any truck loading has been recorded against this order. */
    public function hasLoadings(): bool
    {
        return false;
    }

    public function confirmedQuantityLabel(): ?string
    {
        return self::formatQuantity($this->confirmed_quantity, $this->confirmed_quantity_unit);
    }

    public function rateLabel(): ?string
    {
        if ($this->rate === null) {
            return null;
        }

        return 'Rs '.self::number($this->rate).' '.(config('rice.rate_units')[$this->rate_unit] ?? $this->rate_unit);
    }

    /** Human label for the "where is this order" column. */
    public function stageLabel(): string
    {
        return $this->isConfirmed() && $this->loading_status
            ? $this->loading_status->label()
            : $this->status->label();
    }

    public function stageKey(): string
    {
        return $this->isConfirmed() && $this->loading_status
            ? ($this->loading_status === LoadingStatus::Completed ? 'completed' : ($this->loading_status === LoadingStatus::InProgress ? 'in_progress' : 'confirmed'))
            : $this->status->value;
    }

    public static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }

    public static function formatQuantity(mixed $quantity, ?string $unit): ?string
    {
        if ($quantity === null) {
            return null;
        }

        $units = ['maund' => 'maund', 'kg' => 'kg', 'ton' => 'ton', 'bag' => 'bags'];

        return self::number($quantity).' '.($units[$unit] ?? $unit);
    }

    public function scopeFilter(Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['q'] ?? ''));

        $query
            ->when($term !== '', function (Builder $q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(fn (Builder $w) => $w
                    ->where('dispatch_number', 'like', $like)
                    ->orWhereHas('buyer', fn (Builder $b) => $b->where('name', 'like', $like)->orWhere('city', 'like', $like))
                    ->orWhereHas('sample', fn (Builder $s) => $s->where('sample_number', 'like', $like)
                        ->orWhereHas('mill', fn (Builder $m) => $m->where('name', 'like', $like))));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['loading_status'] ?? null, fn (Builder $q, $status) => $q->where('loading_status', $status))
            ->when($filters['buyer_id'] ?? null, fn (Builder $q, $id) => $q->where('buyer_id', $id))
            ->when($filters['sample_id'] ?? null, fn (Builder $q, $id) => $q->where('sample_id', $id))
            ->when($filters['mill_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('sample', fn (Builder $s) => $s->where('mill_id', $id)))
            ->when($filters['variety_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('sample', fn (Builder $s) => $s->where('rice_variety_id', $id)))
            ->when($filters['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('dispatch_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('dispatch_date', '<=', $date));
    }
}
