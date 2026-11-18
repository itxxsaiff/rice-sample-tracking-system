<?php

namespace App\Http\Controllers;

use App\Http\Requests\Buyers\BuyerRequest;
use App\Enums\LoadingStatus;
use App\Models\Buyer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerController extends Controller
{
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
        $buyer = new Buyer($request->validated());
        $buyer->created_by = $request->user()->id;
        $buyer->save();
        $buyer->recordActivity('created', "Added buyer {$buyer->label()}");

        // Inline "quick add" from the dispatch form.
        if ($request->expectsJson()) {
            return response()->json(['id' => $buyer->id, 'label' => $buyer->name, 'sub' => $buyer->city], 201);
        }

        return redirect()->route('buyers.show', $buyer)->with('success', "{$buyer->name} has been added.");
    }

    public function show(Request $request, Buyer $buyer): View
    {
        $this->authorize('view', $buyer);

        $status = in_array($request->input('status'), ['pending', 'confirmed', 'rejected', 'completed'], true) ? $request->input('status') : null;

        $dispatches = $buyer->dispatches()
            ->with(['sample:id,sample_number,mill_id,rice_variety_id,photo_path', 'sample.mill:id,name,city', 'sample.variety:id,name'])
            ->when($status === 'completed', fn ($q) => $q->where('loading_status', LoadingStatus::Completed))
            ->when($status && $status !== 'completed', fn ($q) => $q->where('status', $status))
            ->latest('dispatch_date')->latest('id')
            ->paginate(15)->withQueryString();

        $counts = $buyer->dispatches()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts['completed'] = $buyer->dispatches()->where('loading_status', LoadingStatus::Completed)->count();
        $counts['all'] = $counts->only(['pending', 'confirmed', 'rejected'])->sum();

        return view('buyers.show', compact('buyer', 'dispatches', 'counts', 'status'));
    }

    public function edit(Buyer $buyer): View
    {
        $this->authorize('update', $buyer);

        return view('buyers.edit', compact('buyer'));
    }

    public function update(BuyerRequest $request, Buyer $buyer): RedirectResponse
    {
        $buyer->update($request->validated());

        return redirect()->route('buyers.show', $buyer)->with('success', 'Buyer details updated.');
    }

    public function destroy(Buyer $buyer): RedirectResponse
    {
        $this->authorize('delete', $buyer);

        $buyer->delete();

        return redirect()->route('buyers.index')->with('success', "{$buyer->name} has been removed.");
    }
}
