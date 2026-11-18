<?php

namespace App\Http\Controllers;

use App\Enums\LoadingStatus;
use App\Http\Requests\Loadings\LoadingPhotosRequest;
use App\Http\Requests\Loadings\LoadingRequest;
use App\Models\Buyer;
use App\Models\Loading;
use App\Models\LoadingPhoto;
use App\Models\SampleDispatch;
use App\Services\LoadingService;
use App\Support\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoadingController extends Controller
{
    public function __construct(private readonly LoadingService $loadings) {}

    /** Loading section: confirmed orders grouped by loading progress. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SampleDispatch::class);

        $tab = in_array($request->input('tab'), ['pending', 'in_progress', 'completed'], true) ? $request->input('tab') : 'pending';
        $filters = $request->only(['q', 'buyer_id', 'from', 'to']);

        $base = fn () => SampleDispatch::query()
            ->where('status', 'confirmed')
            ->filter(collect($filters)->only(['q', 'buyer_id'])->all());

        $orders = $base()
            ->where('loading_status', $tab)
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('confirmation_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('confirmation_date', '<=', $d))
            ->with(['buyer:id,name,city', 'sample:id,sample_number,mill_id,rice_variety_id', 'sample.mill:id,name,city', 'sample.variety:id,name', 'loadings:id,sample_dispatch_id,quantity,quantity_unit,status,truck_number,loading_date'])
            ->orderBy($tab === 'completed' ? 'loading_completed_at' : 'confirmation_date', $tab === 'completed' ? 'desc' : 'asc')
            ->paginate(20)->withQueryString();

        $counts = $base()->selectRaw('loading_status, count(*) as total')->groupBy('loading_status')->pluck('total', 'loading_status');

        return view('loading.index', [
            'orders' => $orders,
            'tab' => $tab,
            'counts' => $counts,
            'filters' => $filters,
            'buyers' => Buyer::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(SampleDispatch $dispatch): View
    {
        $this->authorize('recordLoading', $dispatch);

        $dispatch->load(['buyer', 'sample.mill', 'sample.variety', 'loadings']);

        $loading = new Loading([
            'loading_date' => now(),
            'quantity' => $dispatch->remainingQuantity(),
            'quantity_unit' => $dispatch->confirmed_quantity_unit,
            'packing_type' => $dispatch->packing_type,
        ]);

        return view('loading.create', compact('dispatch', 'loading'));
    }

    public function store(LoadingRequest $request, SampleDispatch $dispatch): RedirectResponse
    {
        $action = $request->input('action', 'save');

        $loading = $this->loadings->create(
            $dispatch, $request->fields(), $request->photos(), $request->user(),
            markLoaded: in_array($action, ['loaded', 'complete'], true),
            completeOrder: $action === 'complete',
        );

        $message = match ($action) {
            'complete' => "Truck {$loading->truck_number} saved and the order is marked as completed.",
            'loaded' => "Truck {$loading->truck_number} saved and marked as loaded.",
            default => "Truck {$loading->truck_number} saved. Add photos or mark it loaded when done.",
        };

        return redirect()->route($action === 'complete' ? 'dispatches.show' : 'loadings.show', $action === 'complete' ? $dispatch : $loading)
            ->with('success', $message);
    }

    public function show(Loading $loading): View
    {
        $this->authorize('view', $loading);

        $loading->load(['dispatch.buyer', 'dispatch.sample.mill', 'dispatch.sample.variety', 'dispatch.loadings', 'photos', 'creator', 'completer', 'activities.user']);

        return view('loading.show', compact('loading'));
    }

    public function edit(Loading $loading): View
    {
        $this->authorize('update', $loading);

        $loading->load(['dispatch.buyer', 'dispatch.sample.mill', 'dispatch.sample.variety', 'dispatch.loadings']);

        return view('loading.edit', ['loading' => $loading, 'dispatch' => $loading->dispatch]);
    }

    public function update(LoadingRequest $request, Loading $loading): RedirectResponse
    {
        $this->loadings->update($loading, $request->fields(), $request->photos(), $request->user());

        return redirect()->route('loadings.show', $loading)->with('success', 'Loading details updated.');
    }

    public function destroy(Loading $loading): RedirectResponse
    {
        $this->authorize('delete', $loading);

        $dispatch = $loading->dispatch;
        $this->loadings->delete($loading);

        return redirect()->route('dispatches.show', $dispatch)->with('success', "Truck loading {$loading->loading_number} removed.");
    }

    public function markLoaded(Request $request, Loading $loading): RedirectResponse
    {
        $this->authorize('markLoaded', $loading);

        $this->loadings->markLoaded($loading, $request->user());

        return back()->with('success', "Truck {$loading->truck_number} marked as loaded.");
    }

    public function storePhotos(LoadingPhotosRequest $request, Loading $loading): RedirectResponse
    {
        $count = $this->loadings->addPhotos($loading, $request->file('photos', []), $request->user());

        return redirect()->route('loadings.show', $loading)->with('success', "{$count} photo(s) uploaded.");
    }

    public function photo(LoadingPhoto $photo): StreamedResponse
    {
        $this->authorize('view', $photo->loading);

        return PhotoStorage::response($photo->path);
    }

    public function destroyPhoto(LoadingPhoto $photo): RedirectResponse
    {
        $this->authorize('deletePhoto', $photo->loading);

        $this->loadings->deletePhoto($photo);

        return back()->with('success', 'Photo removed.');
    }

    public function completeOrder(Request $request, SampleDispatch $dispatch): RedirectResponse
    {
        $this->authorize('completeLoading', $dispatch);

        $this->loadings->completeOrder($dispatch, $request->user());

        return redirect()->route('dispatches.show', $dispatch)->with('success', "Order {$dispatch->dispatch_number} marked as completed.");
    }

    public function reopenOrder(SampleDispatch $dispatch): RedirectResponse
    {
        $this->authorize('reopenLoading', $dispatch);

        $this->loadings->reopenOrder($dispatch);

        return redirect()->route('dispatches.show', $dispatch)->with('success', 'Order reopened for more loading.');
    }
}
