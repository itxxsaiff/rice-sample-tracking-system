<?php

namespace App\Http\Controllers;

use App\Http\Requests\Varieties\RiceVarietyRequest;
use App\Models\RiceVariety;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiceVarietyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', RiceVariety::class);

        $varieties = RiceVariety::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->trim().'%'))
            ->orderByDesc('is_active')->orderBy('name')
            ->get();

        return view('varieties.index', compact('varieties'));
    }

    public function store(RiceVarietyRequest $request): RedirectResponse|JsonResponse
    {
        $variety = RiceVariety::create($request->safe()->only(['name', 'description']));

        if ($request->expectsJson()) {
            return response()->json(['id' => $variety->id, 'label' => $variety->name], 201);
        }

        return redirect()->route('varieties.index')->with('success', "Variety {$variety->name} added.");
    }

    public function edit(RiceVariety $variety): View
    {
        $this->authorize('update', $variety);

        return view('varieties.edit', compact('variety'));
    }

    public function update(RiceVarietyRequest $request, RiceVariety $variety): RedirectResponse
    {
        $variety->fill($request->safe()->only(['name', 'description']));
        $variety->is_active = $request->boolean('is_active');
        $variety->save();

        return redirect()->route('varieties.index')->with('success', "Variety {$variety->name} updated.");
    }

    public function destroy(RiceVariety $variety): RedirectResponse
    {
        $this->authorize('delete', $variety);

        $variety->delete();

        return redirect()->route('varieties.index')->with('success', "Variety {$variety->name} removed.");
    }
}
