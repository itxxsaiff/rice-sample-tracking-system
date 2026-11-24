<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mills\MillRequest;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\SampleDispatch;
use App\Services\DirectoryService;
use App\Support\PdfReport;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MillController extends Controller
{
    public function __construct(private readonly DirectoryService $directory) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Mill::class);

        $mills = Mill::query()
            ->search($request->input('q'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->input('city')))
            ->orderBy('name')
            ->paginate(20)->withQueryString();

        $cities = Mill::query()->distinct()->orderBy('city')->pluck('city');

        return view('mills.index', compact('mills', 'cities'));
    }

    public function create(): View
    {
        $this->authorize('create', Mill::class);

        return view('mills.create', ['mill' => new Mill]);
    }

    public function store(MillRequest $request): RedirectResponse|JsonResponse
    {
        $mill = $this->directory->createMill($request->validated(), $request->user());

        // Inline "quick add" from the sample form.
        if ($request->expectsJson()) {
            return response()->json(['id' => $mill->id, 'label' => $mill->name, 'sub' => $mill->city], 201);
        }

        return redirect()->route('mills.show', $mill)->with('success', "{$mill->name} has been added.");
    }

    public function show(Request $request, Mill $mill): View
    {
        $this->authorize('view', $mill);

        $samples = $mill->samples()
            ->with(['variety:id,name'])
            ->when($request->filled('variety_id'), fn ($q) => $q->where('rice_variety_id', $request->integer('variety_id')))
            ->latest('collection_date')->latest('id')
            ->paginate(15)->withQueryString();

        $stats = [
            'samples' => $mill->samples()->count(),
            'varieties' => $mill->samples()->distinct()->count('rice_variety_id'),
            'last_collected' => $mill->samples()->max('collection_date'),
        ];

        $varieties = RiceVariety::whereIn('id', $mill->samples()->select('rice_variety_id'))->orderBy('name')->pluck('name', 'id');

        return view('mills.show', compact('mill', 'samples', 'stats', 'varieties'));
    }

    /** Mill statement: every sample collected from this mill and what each buyer answered. */
    public function pdf(Request $request, Mill $mill): Response
    {
        $this->authorize('view', $mill);

        $varietyId = $request->integer('variety_id') ?: null;
        $samples = $mill->samples()
            ->with(['variety:id,name', 'dispatches' => fn ($q) => $q->oldest('dispatch_date')->oldest('id'), 'dispatches.buyer:id,name,city'])
            ->when($varietyId, fn ($q) => $q->where('rice_variety_id', $varietyId))
            ->latest('collection_date')->latest('id')
            ->limit(PdfReport::MAX_ROWS)->get();

        $dispatches = new EloquentCollection($samples->flatMap(function ($sample) use ($mill) {
            $sample->setRelation('mill', $mill);

            return $sample->dispatches->each->setRelation('sample', $sample);
        })->sortByDesc(fn (SampleDispatch $d) => [$d->dispatch_date->format('Y-m-d'), $d->id])->values());
        $confirmed = $dispatches->filter->isConfirmed();

        // Chosen before exporting: the samples collected, or what the buyers answered.
        $part = $request->input('part') === 'responses' ? 'responses' : 'samples';
        $sent = $samples->filter(fn ($s) => $s->dispatches->isNotEmpty())->count();

        return PdfReport::download('pdf.mill', [
            'mill' => $mill,
            'part' => $part,
            'samples' => $samples,
            'dispatches' => $dispatches,
            'varietyName' => $varietyId ? RiceVariety::find($varietyId)?->name : null,
            'summary' => $part === 'samples' ? [
                'Samples' => number_format($samples->count()),
                'Varieties' => number_format($samples->pluck('rice_variety_id')->unique()->count()),
                'Sent to buyers' => number_format($sent),
                'Not sent yet' => number_format($samples->count() - $sent),
            ] : [
                'Responses' => number_format($dispatches->count()),
                'Awaiting' => number_format($dispatches->filter->isPending()->count()),
                'Confirmed' => number_format($confirmed->count()),
                'Rejected' => number_format($dispatches->filter->isRejected()->count()),
                'Agreed' => RecordsController::quantities($confirmed, 'confirmed_quantity', 'confirmed_quantity_unit'),
            ],
        ], $part === 'samples' ? 'Mill Samples' : 'Buyer Responses', 'mill-'.$mill->name.'-'.$part);
    }

    public function edit(Mill $mill): View
    {
        $this->authorize('update', $mill);

        return view('mills.edit', compact('mill'));
    }

    public function update(MillRequest $request, Mill $mill): RedirectResponse
    {
        $this->directory->updateMill($mill, $request->validated());

        return redirect()->route('mills.show', $mill)->with('success', 'Mill details updated.');
    }

    public function destroy(Mill $mill): RedirectResponse
    {
        $this->authorize('delete', $mill);

        $mill->delete();

        return redirect()->route('mills.index')->with('success', "{$mill->name} has been removed.");
    }
}
