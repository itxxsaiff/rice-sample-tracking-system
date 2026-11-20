<?php

namespace App\Sync;

use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\SampleDispatch;
use Illuminate\Database\Eloquent\Model;

/**
 * The record shapes the offline app stores. Relations are uuids. Used by the
 * snapshot and by sync responses, so both always agree.
 */
class SyncPresenter
{
    public const TYPES = [
        Mill::class => 'mills', Buyer::class => 'buyers', RiceVariety::class => 'varieties',
        Sample::class => 'samples', SampleDispatch::class => 'dispatches',
        Loading::class => 'loadings',
    ];

    /** @return array{type: string, record: array} */
    public static function present(Model $model): array
    {
        $type = self::TYPES[$model::class];

        return ['type' => $type, 'record' => match ($type) {
            'mills' => self::mill($model), 'buyers' => self::buyer($model), 'varieties' => self::variety($model),
            'samples' => self::sample($model), 'dispatches' => self::dispatch($model),
            'loadings' => self::loading($model),
        }];
    }

    public static function mill(Mill $m): array
    {
        return [
            'uuid' => $m->uuid, 'id' => $m->id, 'name' => $m->name, 'city' => $m->city,
            'contact_person' => $m->contact_person, 'phone' => $m->phone, 'address' => $m->address,
            'notes' => $m->notes, 'version' => (int) $m->version, 'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    public static function buyer(Buyer $b): array
    {
        return [
            'uuid' => $b->uuid, 'id' => $b->id, 'name' => $b->name, 'city' => $b->city,
            'contact_person' => $b->contact_person, 'phone' => $b->phone, 'address' => $b->address,
            'notes' => $b->notes, 'version' => (int) $b->version, 'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    public static function variety(RiceVariety $v): array
    {
        return ['uuid' => $v->uuid, 'id' => $v->id, 'name' => $v->name, 'is_active' => (bool) $v->is_active, 'version' => (int) $v->version];
    }

    public static function sample(Sample $s): array
    {
        return [
            'uuid' => $s->uuid, 'id' => $s->id, 'sample_number' => $s->sample_number,
            'mill_uuid' => $s->mill?->uuid, 'variety_uuid' => $s->variety?->uuid,
            'collection_date' => $s->collection_date->toDateString(),
            'quantity' => $s->quantity !== null ? (float) $s->quantity : null, 'quantity_unit' => $s->quantity_unit,
            'notes' => $s->notes, 'has_photo' => (bool) $s->photo_path, 'version' => (int) $s->version,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }

    public static function dispatch(SampleDispatch $d): array
    {
        return [
            'uuid' => $d->uuid, 'id' => $d->id, 'dispatch_number' => $d->dispatch_number,
            'sample_uuid' => $d->sample?->uuid, 'buyer_uuid' => $d->buyer?->uuid,
            'dispatch_date' => $d->dispatch_date->toDateString(), 'notes' => $d->notes, 'status' => $d->status->value,
            'confirmed_quantity' => $d->confirmed_quantity !== null ? (float) $d->confirmed_quantity : null,
            'confirmed_quantity_unit' => $d->confirmed_quantity_unit, 'rate' => $d->rate !== null ? (float) $d->rate : null,
            'rate_unit' => $d->rate_unit, 'packing_type' => $d->packing_type, 'packing_details' => $d->packing_details,
            'confirmation_date' => $d->confirmation_date?->toDateString(),
            'rejection_reason' => $d->rejection_reason, 'rejection_date' => $d->rejection_date?->toDateString(),
            'loading_status' => $d->loading_status?->value, 'loading_completed_at' => $d->loading_completed_at?->toIso8601String(),
            'version' => (int) $d->version,
        ];
    }

    public static function loading(Loading $l): array
    {
        return [
            'uuid' => $l->uuid, 'id' => $l->id, 'loading_number' => $l->loading_number,
            'dispatch_uuid' => $l->dispatch?->uuid, 'truck_number' => $l->truck_number,
            'loading_date' => $l->loading_date->toDateString(), 'quantity' => (float) $l->quantity, 'quantity_unit' => $l->quantity_unit,
            'bag_count' => $l->bag_count, 'packing_type' => $l->packing_type, 'packing_details' => $l->packing_details,
            'receiver_name' => $l->receiver_name, 'driver_name' => $l->driver_name, 'driver_phone' => $l->driver_phone,
            'notes' => $l->notes, 'status' => $l->status->value,
            'photos_count' => $l->photos_count ?? $l->photos()->count(), 'version' => (int) $l->version,
        ];
    }
}
