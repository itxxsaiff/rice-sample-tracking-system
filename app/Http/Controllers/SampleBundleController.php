<?php

namespace App\Http\Controllers;

use App\Http\Requests\Samples\BundleRequest;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\SampleBundle;
use App\Services\SampleService;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SampleBundleController extends Controller
{
    public function __construct(private readonly SampleService $samples) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SampleBundle::class);

        $bundles = SampleBundle::query()
            ->search($request->input('q'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('bundle_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('bundle_date', '<=', $request->date('to')))
            ->withCount('samples')
            ->with(['samples' => fn ($q) => $q->select('id', 'sample_bundle_id', 'mill_id')->with('mill:id,name')])
            ->latest('bundle_date')->latest('id')
            ->paginate(20)->withQueryString();

        return view('bundles.index', compact('bundles'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SampleBundle::class);

        return view('bundles.create', $this->builderData($request) + [
            'bundle' => new SampleBundle(['bundle_date' => now()]),
            'nextNumber' => ReferenceNumber::peek('bundle'),
        ]);
    }

    public function store(BundleRequest $request): RedirectResponse
    {
        $bundle = $this->samples->createBundle($request->validated(), $request->rows(), $request->user());

        return redirect()->route('bundles.show', $bundle)
            ->with('success', "Bundle {$bundle->bundle_number} saved with ".count($request->rows()).' sample(s).');
    }

    public function show(SampleBundle $bundle): View
    {
        $this->authorize('view', $bundle);

        $bundle->load(['samples.mill', 'samples.variety', 'creator']);

        return view('bundles.show', compact('bundle'));
    }

    public function edit(SampleBundle $bundle): View
    {
        $this->authorize('update', $bundle);

        return view('bundles.edit', compact('bundle'));
    }

    public function update(Request $request, SampleBundle $bundle): RedirectResponse
    {
        $this->authorize('update', $bundle);

        $data = $request->validate([
            'bundle_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\/_. ]*$/', Rule::unique('sample_bundles')->ignore($bundle)],
            'bundle_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $bundle->update(['bundle_number' => strtoupper(trim($data['bundle_number']))] + $data);

        return redirect()->route('bundles.show', $bundle)->with('success', 'Bundle updated.');
    }

    public function destroy(SampleBundle $bundle): RedirectResponse
    {
        $this->authorize('delete', $bundle);

        $bundle->delete();

        return redirect()->route('bundles.index')->with('success', "Bundle {$bundle->bundle_number} deleted.");
    }

    /** Add more sample rows to an existing bundle. */
    public function addSamples(Request $request, SampleBundle $bundle): View
    {
        $this->authorize('update', $bundle);

        return view('bundles.add-samples', $this->builderData($request) + ['bundle' => $bundle]);
    }

    public function storeSamples(BundleRequest $request, SampleBundle $bundle): RedirectResponse
    {
        $added = $this->samples->addToBundle($bundle, $request->rows(), $request->user());

        return redirect()->route('bundles.show', $bundle)->with('success', "{$added->count()} sample(s) added to {$bundle->bundle_number}.");
    }

    /** Old rows (after a validation error) and lookup data for the bundle builder. */
    private function builderData(Request $request): array
    {
        $oldRows = $request->old('samples', []);
        $millIds = collect($oldRows)->pluck('mill_id')->filter()->unique();

        return [
            'oldRows' => $oldRows,
            'oldMills' => $millIds->isEmpty() ? collect() : Mill::whereIn('id', $millIds)->get()->keyBy('id'),
            'varieties' => RiceVariety::options(),
        ];
    }
}
