<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Services\OfflineSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SyncController extends Controller
{
    /**
     * Real connectivity + session check used by the offline app (it does not
     * trust navigator.onLine). Always 200 so the device can tell "server
     * reachable but signed out" apart from "no connection".
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => (bool) $user && $user->is_active,
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value] : null,
            'csrf' => csrf_token(),
            'server_time' => now()->toIso8601String(),
            'max_days_without_server' => config('offline.max_days_without_server'),
        ])->header('Cache-Control', 'no-store');
    }

    /** Offline copy of operational records (full, or changes since ?since=). */
    public function snapshot(Request $request, OfflineSnapshotService $snapshots): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date']]);

        $since = $request->filled('since') ? Carbon::parse($request->input('since')) : null;

        return response()->json($snapshots->build($since))->header('Cache-Control', 'no-store, private');
    }
}
