<?php

namespace App\Models;

use App\Enums\TruckLoadingStatus;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loading extends Model
{
    use HasFactory, RecordsActivity, Syncable;

    protected $fillable = [
        'truck_number', 'loading_date', 'quantity', 'quantity_unit', 'bag_count',
        'packing_type', 'packing_details', 'receiver_name', 'driver_name', 'driver_phone', 'notes',
    ];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'loading_date' => 'date',
            'completed_at' => 'datetime',
            'quantity' => 'decimal:2',
            'bag_count' => 'integer',
            'status' => TruckLoadingStatus::class,
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(SampleDispatch::class, 'sample_dispatch_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(LoadingPhoto::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === TruckLoadingStatus::Completed;
    }

    /**
     * Bags needed for a quantity in a bag size, e.g. 1000 maund in "25 kg" bags = 1600.
     * A part-filled last bag still counts as a bag. Null when it can't be worked out.
     */
    public static function bagsFor(mixed $quantity, ?string $unit, ?string $packing): ?int
    {
        $kgPerUnit = config("rice.unit_kg.{$unit}");
        $bagKg = preg_match('/^\s*(\d+(?:\.\d+)?)\s*kg\s*$/i', (string) $packing, $m) ? (float) $m[1] : null;

        if (! is_numeric($quantity) || (float) $quantity <= 0 || ! $kgPerUnit || ! $bagKg) {
            return null;
        }

        return (int) ceil(round((float) $quantity * $kgPerUnit / $bagKg, 6));
    }

    public function quantityLabel(): string
    {
        return SampleDispatch::formatQuantity($this->quantity, $this->quantity_unit);
    }
}
