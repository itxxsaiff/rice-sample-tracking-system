<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\SampleDispatch;
use App\Support\PdfReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

    private const PDF_TITLES = [
        'dispatches' => 'Dispatch Register',
        'confirmations' => 'Confirmed Orders',
        'rejections' => 'Rejected Samples',
        'loadings' => 'Truck Loading Register',
        'completed' => 'Completed Orders',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SampleDispatch::class);

        [$type, $filters] = $this->selection($request);
        $records = $type === 'loadings' ? $this->loadings($filters) : $this->dispatches($type, $filters);

        return view('records.index', [
            'type' => $type,
            'records' => $records->paginate(25)->withQueryString(),
            'filters' => $filters,
            'buyers' => Buyer::orderBy('name')->pluck('name', 'id'),
            'mills' => Mill::orderBy('name')->get(['id', 'name', 'city'])->mapWithKeys(fn ($m) => [$m->id => "{$m->name} ({$m->city})"]),
            'varieties' => RiceVariety::orderBy('name')->pluck('name', 'id'),
            'dateLabel' => $this->dateLabel($type),
        ]);
    }

    /** The current tab with its filters as a PDF (every matching row, not just one page). */
    public function pdf(Request $request): Response
    {
        $this->authorize('viewAny', SampleDispatch::class);

        [$type, $filters] = $this->selection($request);
        $query = $type === 'loadings' ? $this->loadings($filters) : $this->dispatches($type, $filters);
        $total = $query->count();
        $records = $query->limit(PdfReport::MAX_ROWS)->get();

        return PdfReport::download('pdf.records', [
            'type' => $type,
            'typeLabel' => self::TYPES[$type],
            'records' => $records,
            'total' => $total,
            'truncated' => $total > $records->count(),
            'criteria' => $this->criteria($type, $filters),
            'summary' => $type === 'loadings' ? $this->loadingSummary($records) : self::dispatchSummary($records),
        ], self::PDF_TITLES[$type], 'records-'.$type);
    }

    /** @return array{0: string, 1: array} */
    private function selection(Request $request): array
    {
        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'dispatches';

        return [$type, array_filter($request->only(['q', 'buyer_id', 'mill_id', 'variety_id', 'from', 'to']), fn ($v) => is_string($v) && $v !== '')];
    }

    private function dateLabel(string $type): string
    {
        return match ($type) {
            'confirmations' => 'Confirmation date', 'rejections' => 'Rejection date',
            'loadings' => 'Loading date', 'completed' => 'Completion date', default => 'Dispatch date',
        };
    }

    /** What the printed list covers, in words. */
    private function criteria(string $type, array $filters): array
    {
        return array_filter([
            'List' => self::TYPES[$type],
            'Search' => $filters['q'] ?? null,
            'Mill' => isset($filters['mill_id']) ? Mill::find($filters['mill_id'])?->label() : null,
            'Buyer' => isset($filters['buyer_id']) ? Buyer::find($filters['buyer_id'])?->name : null,
            'Variety' => isset($filters['variety_id']) ? RiceVariety::find($filters['variety_id'])?->name : null,
            $this->dateLabel($type) => PdfReport::period($filters['from'] ?? null, $filters['to'] ?? null),
        ]);
    }

    public static function dispatchSummary(Collection $dispatches): array
    {
        $confirmed = $dispatches->filter->isConfirmed();

        return [
            'Records' => number_format($dispatches->count()),
            'Awaiting response' => number_format($dispatches->filter->isPending()->count()),
            'Confirmed' => number_format($confirmed->count()),
            'Rejected' => number_format($dispatches->filter->isRejected()->count()),
            'Agreed' => self::quantities($confirmed, 'confirmed_quantity', 'confirmed_quantity_unit'),
        ];
    }

    private function loadingSummary(Collection $loadings): array
    {
        return [
            'Trucks' => number_format($loadings->count()),
            'Loaded' => self::quantities($loadings, 'quantity', 'quantity_unit'),
            'Bags' => number_format($loadings->sum('bag_count')),
            'Orders' => number_format($loadings->pluck('sample_dispatch_id')->unique()->count()),
        ];
    }

    /** e.g. "4,000 maund" (one figure per unit). */
    public static function quantities(Collection $rows, string $column, string $unitColumn): string
    {
        return $rows->groupBy($unitColumn)
            ->map(fn ($group, $unit) => SampleDispatch::formatQuantity($group->sum($column), $unit))
            ->implode(', ') ?: '—';
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
