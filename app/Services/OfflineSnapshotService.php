<?php

namespace App\Services;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\SampleBundle;
use App\Models\SampleDispatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds the data a device keeps for offline use. Relations are expressed
 * with uuids (mill_uuid, sample_uuid, …) so the phone can link records it
 * created itself before the server has assigned ids.
 */
class OfflineSnapshotService
{
    /**
     * @param  Carbon|null  $since  only rows changed since this moment (incremental sync); null = everything
     */
    public function build(?Carbon $since = null): array
    {
        $cutoff = now()->subDays(config('offline.history_days'))->startOfDay();
        $changed = fn (Builder $q) => $since ? $q->where($q->getModel()->getTable().'.updated_at', '>=', $since->copy()->subSeconds(5)) : $q;

        // Lookup maps id => uuid (small tables).
        $millUuids = Mill::pluck('uuid', 'id');
        $buyerUuids = Buyer::pluck('uuid', 'id');
        $varietyUuids = RiceVariety::pluck('uuid', 'id');
        $bundleUuids = SampleBundle::pluck('uuid', 'id');

        $openDispatches = fn (Builder $q) => $q->where('status', DispatchStatus::Pending)
            ->orWhere(fn ($w) => $w->where('status', DispatchStatus::Confirmed)->where('loading_status', '!=', LoadingStatus::Completed));

        $dispatchScope = fn (Builder $q) => $q->where(fn ($w) => $w->whereDate('dispatch_date', '>=', $cutoff)->orWhere($openDispatches));
        $sampleScope = fn (Builder $q) => $q->where(fn ($w) => $w->whereDate('collection_date', '>=', $cutoff)
            ->orWhereIn('id', SampleDispatch::query()->where($openDispatches)->select('sample_id')));

        $sampleUuids = Sample::query()->where($sampleScope)->pluck('uuid', 'id');
        $dispatchUuids = SampleDispatch::query()->where($dispatchScope)->pluck('uuid', 'id');

        // Samples referenced by kept dispatches must be present too.
        $missing = SampleDispatch::query()->where($dispatchScope)->whereNotIn('sample_id', $sampleUuids->keys())->pluck('sample_id');
        if ($missing->isNotEmpty()) {
            $sampleUuids = $sampleUuids->union(Sample::whereIn('id', $missing)->pluck('uuid', 'id'));
        }

        return [
            'server_time' => now()->toIso8601String(),
            'incremental' => (bool) $since,
            'history_days' => config('offline.history_days'),

            'mills' => $changed(Mill::query())->get()->map(fn (Mill $m) => [
                'uuid' => $m->uuid, 'id' => $m->id, 'name' => $m->name, 'city' => $m->city,
                'contact_person' => $m->contact_person, 'phone' => $m->phone, 'address' => $m->address,
                'notes' => $m->notes, 'version' => (int) $m->version, 'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),

            'buyers' => $changed(Buyer::query())->get()->map(fn (Buyer $b) => [
                'uuid' => $b->uuid, 'id' => $b->id, 'name' => $b->name, 'city' => $b->city,
                'contact_person' => $b->contact_person, 'phone' => $b->phone, 'address' => $b->address,
                'notes' => $b->notes, 'version' => (int) $b->version, 'created_at' => $b->created_at?->toIso8601String(),
            ])->values(),

            'varieties' => $changed(RiceVariety::query())->get()->map(fn (RiceVariety $v) => [
                'uuid' => $v->uuid, 'id' => $v->id, 'name' => $v->name, 'is_active' => $v->is_active, 'version' => (int) $v->version,
            ])->values(),

            'bundles' => $changed(SampleBundle::query())->get()->map(fn (SampleBundle $b) => [
                'uuid' => $b->uuid, 'id' => $b->id, 'bundle_number' => $b->bundle_number,
                'bundle_date' => $b->bundle_date->toDateString(), 'notes' => $b->notes, 'version' => (int) $b->version,
            ])->values(),

            'samples' => $changed(Sample::query()->whereIn('id', $sampleUuids->keys()))->get()->map(fn (Sample $s) => [
                'uuid' => $s->uuid, 'id' => $s->id, 'sample_number' => $s->sample_number,
                'mill_uuid' => $millUuids[$s->mill_id] ?? null, 'variety_uuid' => $varietyUuids[$s->rice_variety_id] ?? null,
                'bundle_uuid' => $s->sample_bundle_id ? ($bundleUuids[$s->sample_bundle_id] ?? null) : null,
                'collection_date' => $s->collection_date->toDateString(),
                'quantity' => $s->quantity !== null ? (float) $s->quantity : null, 'quantity_unit' => $s->quantity_unit,
                'notes' => $s->notes, 'has_photo' => (bool) $s->photo_path, 'version' => (int) $s->version,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values(),

            'dispatches' => $changed(SampleDispatch::query()->whereIn('id', $dispatchUuids->keys()))->get()->map(fn (SampleDispatch $d) => [
                'uuid' => $d->uuid, 'id' => $d->id, 'dispatch_number' => $d->dispatch_number,
                'sample_uuid' => $sampleUuids[$d->sample_id] ?? null, 'buyer_uuid' => $buyerUuids[$d->buyer_id] ?? null,
                'dispatch_date' => $d->dispatch_date->toDateString(), 'notes' => $d->notes, 'status' => $d->status->value,
                'confirmed_quantity' => $d->confirmed_quantity !== null ? (float) $d->confirmed_quantity : null,
                'confirmed_quantity_unit' => $d->confirmed_quantity_unit, 'rate' => $d->rate !== null ? (float) $d->rate : null,
                'rate_unit' => $d->rate_unit, 'packing_type' => $d->packing_type, 'packing_details' => $d->packing_details,
                'confirmation_date' => $d->confirmation_date?->toDateString(),
                'rejection_reason' => $d->rejection_reason, 'rejection_date' => $d->rejection_date?->toDateString(),
                'loading_status' => $d->loading_status?->value, 'loading_completed_at' => $d->loading_completed_at?->toIso8601String(),
                'version' => (int) $d->version,
            ])->values(),

            'loadings' => $changed(Loading::query()->whereIn('sample_dispatch_id', $dispatchUuids->keys()))->withCount('photos')->get()->map(fn (Loading $l) => [
                'uuid' => $l->uuid, 'id' => $l->id, 'loading_number' => $l->loading_number,
                'dispatch_uuid' => $dispatchUuids[$l->sample_dispatch_id] ?? null, 'truck_number' => $l->truck_number,
                'loading_date' => $l->loading_date->toDateString(), 'quantity' => (float) $l->quantity, 'quantity_unit' => $l->quantity_unit,
                'bag_count' => $l->bag_count, 'packing_type' => $l->packing_type, 'packing_details' => $l->packing_details,
                'receiver_name' => $l->receiver_name, 'driver_name' => $l->driver_name, 'driver_phone' => $l->driver_phone,
                'notes' => $l->notes, 'status' => $l->status->value, 'photos_count' => $l->photos_count, 'version' => (int) $l->version,
            ])->values(),

            // Every uuid the device should still hold, so it can drop records deleted on the server.
            'keep' => [
                'mills' => $millUuids->values(), 'buyers' => $buyerUuids->values(), 'varieties' => $varietyUuids->values(),
                'bundles' => $bundleUuids->values(), 'samples' => $sampleUuids->values(), 'dispatches' => $dispatchUuids->values(),
                'loadings' => Loading::whereIn('sample_dispatch_id', $dispatchUuids->keys())->pluck('uuid')->values(),
            ],
        ];
    }
}
