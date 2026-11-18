<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mills\MillRequest;
use App\Models\Mill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MillController extends Controller
{
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
        $mill = new Mill($request->validated());
        $mill->created_by = $request->user()->id;
        $mill->save();
        $mill->recordActivity('created', "Added rice mill {$mill->label()}");

        // Inline "quick add" from the sample form.
        if ($request->expectsJson()) {
            return response()->json(['id' => $mill->id, 'label' => $mill->name, 'sub' => $mill->city], 201);
        }

        return redirect()->route('mills.show', $mill)->with('success', "{$mill->name} has been added.");
    }

    public function show(Mill $mill): View
    {
        $this->authorize('view', $mill);

        return view('mills.show', compact('mill'));
    }

    public function edit(Mill $mill): View
    {
        $this->authorize('update', $mill);

        return view('mills.edit', compact('mill'));
    }

    public function update(MillRequest $request, Mill $mill): RedirectResponse
    {
        $mill->update($request->validated());

        return redirect()->route('mills.show', $mill)->with('success', 'Mill details updated.');
    }

    public function destroy(Mill $mill): RedirectResponse
    {
        $this->authorize('delete', $mill);

        $mill->delete();

        return redirect()->route('mills.index')->with('success', "{$mill->name} has been removed.");
    }
}
