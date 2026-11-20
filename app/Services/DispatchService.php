<?php

namespace App\Services;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Exceptions\WorkflowException;
use App\Models\Buyer;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\User;
use App\Support\ReferenceNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns the dispatch → response state machine:
 *
 *   pending ──confirm──▶ confirmed ──(loading workflow)
 *      │                    │  ▲
 *      └──reject──▶ rejected│  │ edit details
 *                     │     │
 *   pending ◀──reopen─┴─────┘ (only while no truck has been loaded)
 */
class DispatchService
{
    /** Statuses that block sending the same sample to the same buyer again. */
    public const ACTIVE_STATUSES = [DispatchStatus::Pending, DispatchStatus::Confirmed];

    /**
     * Create one independent dispatch per (sample, buyer) pair.
     *
     * @param  array<string, string>  $uuids  optional "sampleId:buyerId" => uuid (from the offline app)
     * @return Collection<int, SampleDispatch>
     */
    public function dispatch(array $sampleIds, array $buyerIds, string $date, ?string $notes, User $user, array $uuids = []): Collection
    {
        return DB::transaction(function () use ($sampleIds, $buyerIds, $date, $notes, $user, $uuids) {
            $samples = Sample::with('mill')->findMany($sampleIds)->keyBy('id');
            $buyers = Buyer::findMany($buyerIds)->keyBy('id');

            if ($duplicates = $this->activeDuplicates($sampleIds, $buyerIds)) {
                throw new WorkflowException('Already sent and still active: '.implode(', ', $duplicates).'.');
            }

            $created = collect();

            foreach ($samples as $sample) {
                foreach ($buyers as $buyer) {
                    $dispatch = new SampleDispatch([
                        'sample_id' => $sample->id,
                        'buyer_id' => $buyer->id,
                        'dispatch_date' => $date,
                        'notes' => $notes,
                    ]);
                    $dispatch->dispatch_number = ReferenceNumber::next('dispatch', SampleDispatch::class, 'dispatch_number');
                    if ($uuid = $uuids["{$sample->id}:{$buyer->id}"] ?? null) {
                        $dispatch->uuid = $uuid;
                    }
                    $dispatch->status = DispatchStatus::Pending;
                    $dispatch->created_by = $user->id;
                    $dispatch->save();

                    $dispatch->recordActivity('dispatched', "Sent sample {$sample->sample_number} to {$buyer->name}");
                    $sample->recordActivity('dispatched', "Dispatched to {$buyer->name} ({$dispatch->dispatch_number})");
                    $created->push($dispatch);
                }
            }

            return $created;
        });
    }

    /**
     * Human-readable list of (sample → buyer) pairs that already have a
     * pending or confirmed dispatch.
     *
     * @return list<string>
     */
    public function activeDuplicates(array $sampleIds, array $buyerIds): array
    {
        return SampleDispatch::query()
            ->with(['sample:id,sample_number', 'buyer:id,name'])
            ->whereIn('sample_id', $sampleIds)
            ->whereIn('buyer_id', $buyerIds)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->get()
            ->map(fn (SampleDispatch $d) => "{$d->sample->sample_number} → {$d->buyer->name}")
            ->all();
    }

    /** Record the buyer's answer to a pending dispatch, or correct an existing answer. */
    public function respond(SampleDispatch $dispatch, array $data, User $user): SampleDispatch
    {
        return DB::transaction(function () use ($dispatch, $data, $user) {
            $dispatch = SampleDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $target = DispatchStatus::from($data['status']);

            $this->assertTransition($dispatch, $target);

            $isCorrection = $dispatch->status === $target;

            if ($target === DispatchStatus::Confirmed) {
                $dispatch->forceFill([
                    'confirmed_quantity' => $data['confirmed_quantity'],
                    'confirmed_quantity_unit' => $data['confirmed_quantity_unit'] ?? config('rice.order_unit'),
                    'rate' => $data['rate'],
                    'rate_unit' => $data['rate_unit'] ?? config('rice.order_rate_unit'),
                    'packing_type' => $data['packing_type'],
                    'packing_details' => $data['packing_details'] ?? null,
                    'confirmation_date' => $data['confirmation_date'],
                    'rejection_reason' => null,
                    'rejection_date' => null,
                ]);
                // Entering the loading workflow; corrections keep current loading progress.
                $dispatch->loading_status ??= LoadingStatus::Pending;
            } else {
                $dispatch->forceFill([
                    'rejection_reason' => null,
                    'rejection_date' => $data['rejection_date'] ?? now()->toDateString(),
                    'confirmed_quantity' => null,
                    'confirmed_quantity_unit' => null,
                    'rate' => null,
                    'rate_unit' => null,
                    'packing_type' => null,
                    'packing_details' => null,
                    'confirmation_date' => null,
                    'loading_status' => null,
                ]);
            }

            $dispatch->status = $target;
            $dispatch->responded_by = $user->id;
            $dispatch->responded_at = now();
            $dispatch->save();

            $dispatch->load('buyer');
            $description = match (true) {
                $isCorrection => "Updated {$target->label()} details for {$dispatch->buyer->name}",
                $target === DispatchStatus::Confirmed => "{$dispatch->buyer->name} confirmed {$dispatch->confirmedQuantityLabel()} at {$dispatch->rateLabel()}",
                default => "{$dispatch->buyer->name} rejected the sample",
            };

            $dispatch->recordActivity($isCorrection ? 'updated' : $target->value, $description);

            return $dispatch;
        });
    }

    /** Return a responded dispatch to "awaiting response", keeping the old answer in the activity log. */
    public function reopen(SampleDispatch $dispatch, User $user): SampleDispatch
    {
        return DB::transaction(function () use ($dispatch) {
            $dispatch = SampleDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);

            if ($dispatch->isPending()) {
                throw new WorkflowException('This dispatch is already awaiting a response.');
            }
            if ($dispatch->hasLoadings()) {
                throw new WorkflowException('Loading has already been recorded for this order, so the response can no longer be reopened.');
            }

            $previous = $dispatch->only([
                'status', 'confirmed_quantity', 'confirmed_quantity_unit', 'rate', 'rate_unit',
                'packing_type', 'packing_details', 'confirmation_date', 'rejection_reason', 'rejection_date',
            ]);

            $dispatch->forceFill(array_fill_keys(array_keys($previous), null));
            $dispatch->status = DispatchStatus::Pending;
            $dispatch->loading_status = null;
            $dispatch->responded_by = null;
            $dispatch->responded_at = null;
            $dispatch->save();

            $dispatch->recordActivity('reopened', 'Response reopened — awaiting buyer again', [
                'previous' => array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v), $previous),
            ]);

            return $dispatch;
        });
    }

    private function assertTransition(SampleDispatch $dispatch, DispatchStatus $target): void
    {
        if ($target === DispatchStatus::Pending) {
            throw new WorkflowException('Choose whether the buyer confirmed or rejected the sample.');
        }

        if ($dispatch->isPending() || $dispatch->status === $target) {
            return; // first response, or correcting details of the same response
        }

        if ($dispatch->isConfirmed() && $dispatch->hasLoadings()) {
            throw new WorkflowException('This order already has loading records and cannot be changed to rejected.');
        }

        throw new WorkflowException("This dispatch is already {$dispatch->status->label()}. Reopen it first to record a different response.");
    }
}
