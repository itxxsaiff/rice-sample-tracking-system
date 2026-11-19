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

    public function quantityLabel(): string
    {
        return SampleDispatch::formatQuantity($this->quantity, $this->quantity_unit);
    }
}
