<?php

namespace App\Http\Controllers;

use App\Enums\LoadingStatus;
use App\Http\Requests\Buyers\BuyerRequest;
use App\Models\Buyer;
use App\Models\Loading;
use App\Services\DirectoryService;
use App\Support\PdfReport;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BuyerController extends Controller
{
    /** Status tabs on the buyer page. */
    private const STATUSES = ['pending' => 'Awaiting response', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'completed' => 'Completed orders'];

    public function __construct(private readonly DirectoryService $directory) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Buyer::class);

        $buyers = Buyer::query()
            ->search($request->input('q'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->input('city')))
            ->orderBy('name')
            ->paginate(20)->withQueryString();

        $cities = Buyer::query()->distinct()->orderBy('city')->pluck('city');

        return view('buyers.index', compact('buyers', 'cities'));
    }

    public function create(): View
    {
        $this->authorize('create', Buyer::class);

        return view('buyers.create', ['buyer' => new Buyer]);
    }

    public function store(BuyerRequest $request): RedirectResponse|JsonResponse
    {
        $buyer = $this->directory->createBuyer($request->validated(), $request->user());

        // Inline "quick add" from the dispatch form.
        if ($request->expectsJson()) {
            return response()->json(['id' => $buyer->id, 'label' => $buyer->name, 'sub' => $buyer->city], 201);
        }

        return redirect()->route('buyers.show', $buyer)->with('success', "{$buyer->name} has been added.");
    }

    public function show(Request $request, Buyer $buyer): View
    {
        $this->authorize('view', $buyer);

        $status = $this->statusFilter($request);

        $dispatches = $this->dispatchQuery($buyer, $status)
            ->with(['sample:id,sample_number,mill_id,rice_variety_id,photo_path', 'sample.mill:id,name,city', 'sample.variety:id,name'])
            ->paginate(15)->withQueryString();
        $dispatches->getCollection()->each->setRelation('buyer', $buyer);

        $counts = $buyer->dispatches()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts['completed'] = $buyer->dispatches()->where('loading_status', LoadingStatus::Completed)->count();
        $counts['all'] = $counts->only(['pending', 'confirmed', 'rejected'])->sum();

        return view('buyers.show', compact('buyer', 'dispatches', 'counts', 'status'));
    }

    /** Buyer statement: every sample sent to this buyer, the answers and the trucks loaded. */
    public function pdf(Request $request, Buyer $buyer): Response
    {
        $this->authorize('view', $buyer);

        $status = $this->statusFilter($request);
        $dispatches = $this->dispatchQuery($buyer, $status)
            ->with(['sample:id,sample_number,mill_id,rice_variety_id', 'sample.mill:id,name,city', 'sample.variety:id,name', 'loadings'])
            ->limit(PdfReport::MAX_ROWS)->get();
        $dispatches->each->setRelation('buyer', $buyer);

        // Chosen before exporting: the samples sent, or the trucks loaded (with their photos).
        $part = $request->input('part') === 'loadings' ? 'loadings' : 'samples';
        $loadings = Loading::query()
            ->whereIn('sample_dispatch_id', $dispatches->modelKeys())
            ->with(['dispatch:id,dispatch_number,buyer_id,sample_id', 'dispatch.sample:id,sample_number,mill_id,rice_variety_id', 'dispatch.sample.variety:id,name'])
            ->with($part === 'loadings' ? ['photos'] : [])
            ->withCount('photos')
            ->oldest('loading_date')->oldest('id')
            ->get();
        $confirmed = $dispatches->filter->isConfirmed();

        $summary = $part === 'samples' ? [
            'Samples sent' => number_format($dispatches->count()),
            'Awaiting' => number_format($dispatches->filter->isPending()->count()),
            'Confirmed' => number_format($confirmed->count()),
            'Rejected' => number_format($dispatches->filter->isRejected()->count()),
            'Agreed' => RecordsController::quantities($confirmed, 'confirmed_quantity', 'confirmed_quantity_unit'),
            'Loaded' => RecordsController::quantities($loadings, 'quantity', 'quantity_unit'),
        ] : [
            'Trucks' => number_format($loadings->count()),
            'Loaded' => RecordsController::quantities($loadings, 'quantity', 'quantity_unit'),
            'Bags' => number_format($loadings->sum('bag_count')),
            'Orders' => number_format($loadings->pluck('sample_dispatch_id')->unique()->count()),
            'Photos' => number_format($loadings->sum('photos_count')),
        ];

        return PdfReport::download('pdf.buyer', [
            'buyer' => $buyer,
            'part' => $part,
            'dispatches' => $dispatches,
            'loadings' => $loadings,
            'statusLabel' => $status ? self::STATUSES[$status] : null,
            'summary' => $summary,
        ], $part === 'samples' ? 'Samples Sent' : 'Truck Loadings', 'buyer-'.$buyer->name.'-'.($part === 'samples' ? 'samples' : 'trucks'));
    }

    public function edit(Buyer $buyer): View
    {
        $this->authorize('update', $buyer);

        return view('buyers.edit', compact('buyer'));
    }

    public function update(BuyerRequest $request, Buyer $buyer): RedirectResponse
    {
        $this->directory->updateBuyer($buyer, $request->validated());

        return redirect()->route('buyers.show', $buyer)->with('success', 'Buyer details updated.');
    }

    private function statusFilter(Request $request): ?string
    {
        return array_key_exists((string) $request->input('status'), self::STATUSES) ? $request->input('status') : null;
    }

    private function dispatchQuery(Buyer $buyer, ?string $status): HasMany
    {
        return $buyer->dispatches()
            ->when($status === 'completed', fn ($q) => $q->where('loading_status', LoadingStatus::Completed))
            ->when($status && $status !== 'completed', fn ($q) => $q->where('status', $status))
            ->latest('dispatch_date')->latest('id');
    }

    public function destroy(Buyer $buyer): RedirectResponse
    {
        $this->authorize('delete', $buyer);

        $buyer->delete();

        return redirect()->route('buyers.index')->with('success', "{$buyer->name} has been removed.");
    }
}
