<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\Mill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON search endpoints for the searchable pickers used in mobile forms.
 */
class LookupController extends Controller
{
    private const LIMIT = 20;

    public function mills(Request $request): JsonResponse
    {
        $mills = Mill::query()->search($request->input('q'))
            ->orderBy('name')->limit(self::LIMIT)->get(['id', 'name', 'city']);

        return response()->json([
            'data' => $mills->map(fn (Mill $m) => ['id' => $m->id, 'label' => $m->name, 'sub' => $m->city]),
        ]);
    }

    public function buyers(Request $request): JsonResponse
    {
        $buyers = Buyer::query()->search($request->input('q'))
            ->orderBy('name')->limit(self::LIMIT)->get(['id', 'name', 'city']);

        return response()->json([
            'data' => $buyers->map(fn (Buyer $b) => ['id' => $b->id, 'label' => $b->name, 'sub' => $b->city]),
        ]);
    }
}
