<?php

namespace App\Http\Controllers;

use App\Http\Requests\Samples\SampleRequest;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\SampleBundle;
use App\Services\SampleService;
use App\Support\PhotoStorage;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SampleController extends Controller
{
    public function __construct(private readonly SampleService $samples) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sample::class);

        $filters = $request->only(['q', 'mill_id', 'variety_id', 'bundle_id', 'city', 'from', 'to', 'sent']);

        $samples = Sample::query()
            ->with(['mill:id,name,city', 'variety:id,name', 'bundle:id,bundle_number'])
            ->withCount('dispatches')
            ->filter($filters)
            ->latest('collection_date')->latest('id')
            ->paginate(25)->withQueryString();

        return view('samples.index', [
            'samples' => $samples,
            'filters' => $filters,
            'filterMill' => isset($filters['mill_id']) ? Mill::find($filters['mill_id']) : null,
            'varieties' => RiceVariety::orderBy('name')->pluck('name', 'id'),
            'cities' => Mill::query()->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Sample::class);

        $sample = new Sample([
            'collection_date' => now(),
            'quantity_unit' => 'g',
            'mill_id' => $request->integer('mill_id') ?: null,
            'sample_bundle_id' => $request->integer('bundle_id') ?: null,
        ]);

        return view('samples.create', $this->formData($sample) + [
            'nextNumber' => ReferenceNumber::peek('sample'),
        ]);
    }

    public function store(SampleRequest $request): RedirectResponse
    {
        $sample = $this->samples->create($request->validated(), $request->user());

        $redirect = $request->input('after') === 'another'
            ? redirect()->route('samples.create', ['mill_id' => $sample->mill_id, 'bundle_id' => $sample->sample_bundle_id])
            : redirect()->route('samples.show', $sample);

        return $redirect->with('success', "Sample {$sample->sample_number} saved.");
    }

    public function show(Sample $sample): View
    {
        $this->authorize('view', $sample);

        $sample->load(['mill', 'variety', 'bundle', 'creator', 'activities.user', 'dispatches' => fn ($q) => $q->with('buyer:id,name,city')->latest('dispatch_date')->latest('id')]);

        return view('samples.show', compact('sample'));
    }

    public function edit(Sample $sample): View
    {
        $this->authorize('update', $sample);

        $sample->load('mill');

        return view('samples.edit', $this->formData($sample));
    }

    public function update(SampleRequest $request, Sample $sample): RedirectResponse
    {
        $this->samples->update($sample, $request->validated(), $request->boolean('remove_photo'));

        return redirect()->route('samples.show', $sample)->with('success', 'Sample updated.');
    }

    public function destroy(Sample $sample): RedirectResponse
    {
        $this->authorize('delete', $sample);

        $this->samples->delete($sample);

        return redirect()->route('samples.index')->with('success', "Sample {$sample->sample_number} deleted.");
    }

    public function photo(Sample $sample): StreamedResponse
    {
        $this->authorize('view', $sample);
        abort_unless($sample->photo_path, 404);

        return PhotoStorage::response($sample->photo_path);
    }

    private function formData(Sample $sample): array
    {
        $millId = old('mill_id', $sample->mill_id);

        return [
            'sample' => $sample,
            'selectedMill' => $millId ? Mill::find($millId) : null,
            'varieties' => RiceVariety::options($sample->rice_variety_id),
            'bundles' => SampleBundle::query()->withCount('samples')->latest('bundle_date')->latest('id')->limit(30)->get()
                ->when($sample->sample_bundle_id, fn ($c) => $c->contains('id', $sample->sample_bundle_id) ? $c : $c->push(SampleBundle::withCount('samples')->find($sample->sample_bundle_id))),
        ];
    }
}
