<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Http\Requests\Dispatches\RespondDispatchRequest;
use App\Http\Requests\Dispatches\StoreDispatchRequest;
use App\Models\Buyer;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Services\DispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SampleDispatchController extends Controller
{
    public function __construct(private readonly DispatchService $dispatches) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SampleDispatch::class);

        $filters = $request->only(['q', 'status', 'buyer_id', 'mill_id', 'variety_id', 'from', 'to']);

        $dispatches = SampleDispatch::query()
            ->with(['buyer:id,name,city', 'sample:id,sample_number,mill_id,rice_variety_id,photo_path', 'sample.mill:id,name,city', 'sample.variety:id,name'])
            ->filter($filters)
            ->latest('dispatch_date')->latest('id')
            ->paginate(25)->withQueryString();

        $counts = SampleDispatch::query()->filter(collect($filters)->except('status')->all())
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('dispatches.index', [
            'dispatches' => $dispatches,
            'filters' => $filters,
            'counts' => $counts,
            'buyers' => Buyer::orderBy('name')->pluck('name', 'id'),
            'varieties' => RiceVariety::orderBy('name')->pluck('name', 'id'),
            'filterMill' => isset($filters['mill_id']) ? Mill::find($filters['mill_id']) : null,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SampleDispatch::class);

        $sampleIds = collect($request->old('sample_ids', $request->input('sample_ids', [])))->filter()->map(fn ($id) => (int) $id);
        $selectedSamples = Sample::with(['mill:id,name,city', 'variety:id,name'])->findMany($sampleIds);

        return view('dispatches.create', [
            'selectedSamples' => $selectedSamples->map(fn (Sample $s) => self::sampleOption($s))->values(),
            'buyers' => Buyer::orderBy('name')->get(['id', 'name', 'city']),
            'selectedBuyers' => collect($request->old('buyer_ids', $request->input('buyer_ids', [])))->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function store(StoreDispatchRequest $request): RedirectResponse
    {
        $created = $this->dispatches->dispatch(
            $request->validated('sample_ids'),
            $request->validated('buyer_ids'),
            $request->validated('dispatch_date'),
            $request->validated('notes'),
            $request->user(),
        );

        if ($created->count() === 1) {
            return redirect()->route('dispatches.show', $created->first())
                ->with('success', "Dispatch {$created->first()->dispatch_number} recorded.");
        }

        return redirect()->route('dispatches.index', ['status' => 'pending'])
            ->with('success', "{$created->count()} dispatches recorded ({$created->first()->dispatch_number} – {$created->last()->dispatch_number}).");
    }

    public function show(SampleDispatch $dispatch): View
    {
        $this->authorize('view', $dispatch);

        $dispatch->load(['buyer', 'sample.mill', 'sample.variety', 'sample.bundle', 'creator', 'responder', 'activities.user', 'loadings' => fn ($q) => $q->withCount('photos')]);

        // Same sample's other dispatches, to compare buyer responses side by side.
        $siblings = SampleDispatch::with('buyer:id,name,city')
            ->where('sample_id', $dispatch->sample_id)->whereKeyNot($dispatch->id)
            ->latest('dispatch_date')->get();

        return view('dispatches.show', compact('dispatch', 'siblings'));
    }

    public function respondForm(SampleDispatch $dispatch): View
    {
        $this->authorize('respond', $dispatch);

        $dispatch->load(['buyer', 'sample.mill', 'sample.variety']);

        return view('dispatches.respond', compact('dispatch'));
    }

    public function respond(RespondDispatchRequest $request, SampleDispatch $dispatch): RedirectResponse
    {
        $dispatch = $this->dispatches->respond($dispatch, $request->validated(), $request->user());

        $message = $dispatch->isConfirmed()
            ? "Confirmed by {$dispatch->buyer->name}. The order is now in the Loading section."
            : "Marked as rejected by {$dispatch->buyer->name}.";

        return redirect()->route('dispatches.show', $dispatch)->with('success', $message);
    }

    public function reopen(Request $request, SampleDispatch $dispatch): RedirectResponse
    {
        $this->authorize('reopen', $dispatch);

        $this->dispatches->reopen($dispatch, $request->user());

        return redirect()->route('dispatches.show', $dispatch)->with('success', 'Response reopened. The dispatch is awaiting the buyer again.');
    }

    public function destroy(SampleDispatch $dispatch): RedirectResponse
    {
        $this->authorize('delete', $dispatch);

        $dispatch->delete();

        return redirect()->route('dispatches.index')->with('success', "Dispatch {$dispatch->dispatch_number} deleted.");
    }

    public static function sampleOption(Sample $sample): array
    {
        return [
            'id' => $sample->id,
            'label' => $sample->sample_number.' · '.$sample->variety->name,
            'sub' => $sample->mill->name.', '.$sample->mill->city.' · '.$sample->collection_date->format('d M Y'),
        ];
    }
}
