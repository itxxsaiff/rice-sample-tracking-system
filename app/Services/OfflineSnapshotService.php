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
use App\Sync\SyncPresenter;
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

            'mills' => $changed(Mill::query())->get()->map(SyncPresenter::mill(...))->values(),
            'buyers' => $changed(Buyer::query())->get()->map(SyncPresenter::buyer(...))->values(),
            'varieties' => $changed(RiceVariety::query())->get()->map(SyncPresenter::variety(...))->values(),
            'bundles' => $changed(SampleBundle::query())->get()->map(SyncPresenter::bundle(...))->values(),
            'samples' => $changed(Sample::query()->whereIn('id', $sampleUuids->keys()))
                ->with(['mill:id,uuid', 'variety:id,uuid', 'bundle:id,uuid'])->get()->map(SyncPresenter::sample(...))->values(),
            'dispatches' => $changed(SampleDispatch::query()->whereIn('id', $dispatchUuids->keys()))
                ->with(['sample:id,uuid', 'buyer:id,uuid'])->get()->map(SyncPresenter::dispatch(...))->values(),
            'loadings' => $changed(Loading::query()->whereIn('sample_dispatch_id', $dispatchUuids->keys()))
                ->with('dispatch:id,uuid')->withCount('photos')->get()->map(SyncPresenter::loading(...))->values(),

            // Every uuid the device should still hold, so it can drop records deleted on the server.
            'keep' => [
                'mills' => Mill::pluck('uuid'), 'buyers' => Buyer::pluck('uuid'), 'varieties' => RiceVariety::pluck('uuid'),
                'bundles' => SampleBundle::pluck('uuid'), 'samples' => $sampleUuids->values(), 'dispatches' => $dispatchUuids->values(),
                'loadings' => Loading::whereIn('sample_dispatch_id', $dispatchUuids->keys())->pluck('uuid')->values(),
            ],
        ];
    }
}
