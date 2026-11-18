<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\SampleDispatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Permanent, filterable history of dispatches, confirmations, rejections,
 * truck loadings and completed orders.
 */
class RecordsController extends Controller
{
    public const TYPES = [
        'dispatches' => 'All dispatches',
        'confirmations' => 'Confirmations',
        'rejections' => 'Rejections',
        'loadings' => 'Truck loadings',
        'completed' => 'Completed orders',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SampleDispatch::class);

        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'dispatches';
        $filters = $request->only(['q', 'buyer_id', 'mill_id', 'variety_id', 'from', 'to']);

        $records = $type === 'loadings' ? $this->loadings($filters) : $this->dispatches($type, $filters);

        return view('records.index', [
            'type' => $type,
            'records' => $records->paginate(25)->withQueryString(),
            'filters' => $filters,
            'buyers' => Buyer::orderBy('name')->pluck('name', 'id'),
            'mills' => Mill::orderBy('name')->get(['id', 'name', 'city'])->mapWithKeys(fn ($m) => [$m->id => "{$m->name} ({$m->city})"]),
            'varieties' => RiceVariety::orderBy('name')->pluck('name', 'id'),
            'dateLabel' => match ($type) {
                'confirmations' => 'Confirmation date', 'rejections' => 'Rejection date',
                'loadings' => 'Loading date', 'completed' => 'Completion date', default => 'Dispatch date',
            },
        ]);
    }

    private function dispatches(string $type, array $filters): Builder
    {
        [$dateColumn, $order] = match ($type) {
            'confirmations' => ['confirmation_date', 'confirmation_date'],
            'rejections' => ['rejection_date', 'rejection_date'],
            'completed' => ['loading_completed_at', 'loading_completed_at'],
            default => ['dispatch_date', 'dispatch_date'],
        };

        return SampleDispatch::query()
            ->with(['buyer:id,name,city', 'sample:id,sample_number,mill_id,rice_variety_id', 'sample.mill:id,name,city', 'sample.variety:id,name'])
            ->filter(collect($filters)->except(['from', 'to'])->all())
            ->when($type === 'confirmations', fn ($q) => $q->where('status', DispatchStatus::Confirmed))
            ->when($type === 'rejections', fn ($q) => $q->where('status', DispatchStatus::Rejected))
            ->when($type === 'completed', fn ($q) => $q->where('loading_status', LoadingStatus::Completed))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate($dateColumn, '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate($dateColumn, '<=', $d))
            ->latest($order)->latest('id');
    }

    private function loadings(array $filters): Builder
    {
        $term = trim((string) ($filters['q'] ?? ''));
        $dispatchFilters = collect($filters)->only(['buyer_id', 'mill_id', 'variety_id'])->filter()->all();

        return Loading::query()
            ->with(['dispatch:id,dispatch_number,buyer_id,sample_id,loading_status', 'dispatch.buyer:id,name', 'dispatch.sample:id,sample_number,mill_id,rice_variety_id', 'dispatch.sample.mill:id,name', 'dispatch.sample.variety:id,name'])
            ->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('truck_number', 'like', "%{$term}%")
                ->orWhere('loading_number', 'like', "%{$term}%")
                ->orWhere('driver_name', 'like', "%{$term}%")
                ->orWhereHas('dispatch', fn ($d) => $d->filter(['q' => $term]))))
            ->when($dispatchFilters, fn ($q) => $q->whereHas('dispatch', fn ($d) => $d->filter($dispatchFilters)))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('loading_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('loading_date', '<=', $d))
            ->latest('loading_date')->latest('id');
    }
}
