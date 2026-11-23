<?php

namespace App\Http\Controllers;

use App\Http\Requests\Samples\SampleRequest;
use App\Http\Requests\Samples\StoreSamplesRequest;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Services\SampleService;
use App\Support\PdfReport;
use App\Support\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SampleController extends Controller
{
    public function __construct(private readonly SampleService $samples) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sample::class);

        $filters = $request->only(['q', 'mill_id', 'variety_id', 'city', 'from', 'to', 'sent']);

        $samples = Sample::query()
            ->with(['mill:id,name,city', 'variety:id,name'])
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

    /** The samples list with its filters as a PDF (every matching sample, with the buyers it went to). */
    public function pdf(Request $request): Response
    {
        $this->authorize('viewAny', Sample::class);

        $filters = array_filter($request->only(['q', 'mill_id', 'variety_id', 'city', 'from', 'to', 'sent']), fn ($v) => is_string($v) && $v !== '');
        $query = Sample::query()->filter($filters);
        $total = $query->count();
        $samples = $query
            ->with(['mill:id,name,city', 'variety:id,name', 'dispatches' => fn ($q) => $q->oldest('dispatch_date')->oldest('id'), 'dispatches.buyer:id,name'])
            ->latest('collection_date')->latest('id')
            ->limit(PdfReport::MAX_ROWS)->get();

        $date = fn (string $d) => rescue(fn () => Carbon::parse($d)->format('d M Y'), $d, false);
        $sent = $samples->filter(fn (Sample $s) => $s->dispatches->isNotEmpty());

        return PdfReport::download('pdf.samples', [
            'samples' => $samples,
            'total' => $total,
            'truncated' => $total > $samples->count(),
            'criteria' => array_filter([
                'Search' => $filters['q'] ?? null,
                'Mill' => isset($filters['mill_id']) ? Mill::find($filters['mill_id'])?->label() : null,
                'Variety' => isset($filters['variety_id']) ? RiceVariety::find($filters['variety_id'])?->name : null,
                'City' => $filters['city'] ?? null,
                'Collected' => match (true) {
                    isset($filters['from'], $filters['to']) => $date($filters['from']).' – '.$date($filters['to']),
                    isset($filters['from']) => 'from '.$date($filters['from']),
                    isset($filters['to']) => 'up to '.$date($filters['to']),
                    default => 'All dates',
                },
                'Showing' => ['no' => 'Not sent yet', 'yes' => 'Sent to buyers'][$filters['sent'] ?? ''] ?? null,
            ]),
            'summary' => [
                'Samples' => number_format($samples->count()),
                'Mills' => number_format($samples->pluck('mill_id')->unique()->count()),
                'Varieties' => number_format($samples->pluck('rice_variety_id')->unique()->count()),
                'Sent to buyers' => number_format($sent->count()),
                'Not sent yet' => number_format($samples->count() - $sent->count()),
            ],
        ], 'Sample Register', 'samples');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Sample::class);

        $millId = $request->integer('mill_id') ?: null;
        $rows = old('samples', [['mill_id' => $millId, 'collection_date' => now()->format('Y-m-d')]]);
        $millIds = collect($rows)->pluck('mill_id')->filter()->unique();

        return view('samples.create', [
            'rows' => $rows,
            'mills' => Mill::whereIn('id', $millIds)->get()->keyBy('id'),
            'varieties' => RiceVariety::options(),
        ]);
    }

    public function store(StoreSamplesRequest $request): RedirectResponse
    {
        $samples = $this->samples->createMany($request->rows(), $request->user());

        if ($samples->count() === 1) {
            return redirect()->route('samples.show', $samples->first())
                ->with('success', "Sample {$samples->first()->sample_number} saved.");
        }

        return redirect()->route('samples.index')
            ->with('success', "{$samples->count()} samples saved ({$samples->first()->sample_number} – {$samples->last()->sample_number}).");
    }

    public function show(Sample $sample): View
    {
        $this->authorize('view', $sample);

        $sample->load(['mill', 'variety', 'creator', 'activities.user', 'dispatches' => fn ($q) => $q->with('buyer:id,name,city')->latest('dispatch_date')->latest('id')]);

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
        $this->samples->update($sample, $request->validated());

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
        ];
    }
}
