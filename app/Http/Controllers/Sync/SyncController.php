<?php

namespace App\Http\Controllers\Sync;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Services\OfflineSnapshotService;
use App\Sync\MissingReference;
use App\Sync\SyncConflict;
use App\Sync\SyncOperationService;
use App\Sync\SyncPresenter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /**
     * Apply one queued offline operation (multipart: "operation" JSON + optional "photo").
     * Responses tell the device exactly what to do with the queued item.
     */
    public function operation(Request $request, SyncOperationService $sync): JsonResponse
    {
        $op = json_decode((string) $request->input('operation'), true);

        $validator = Validator::make(is_array($op) ? $op : [], [
            'uuid' => ['required', 'uuid'],
            'type' => ['required', Rule::in(SyncOperationService::TYPES)],
            'payload' => ['present', 'array'],
        ]);
        if ($validator->fails()) {
            return $this->outcome('rejected', 'This change could not be read by the server.', 422, ['errors' => $validator->errors()]);
        }

        try {
            return response()->json(['status' => 'applied'] + $sync->apply($op, $request->user(), $request->file('photo')));
        } catch (ValidationException $e) {
            return $this->outcome('rejected', collect($e->errors())->flatten()->first() ?? 'Some details are not valid.', 422, ['errors' => $e->errors()]);
        } catch (AuthorizationException $e) {
            return $this->outcome('forbidden', 'Your account is not allowed to make this change.', 403);
        } catch (SyncConflict $e) {
            return $this->outcome('conflict', $e->getMessage(), 409, ['server' => SyncPresenter::present($e->current->fresh())]);
        } catch (MissingReference $e) {
            return $this->outcome('missing_reference', $e->getMessage(), 424, ['reference' => $e->reference]);
        } catch (WorkflowException $e) {
            return $this->outcome('rejected', $e->getMessage(), 422);
        }
    }

    private function outcome(string $status, string $message, int $code, array $extra = []): JsonResponse
    {
        return response()->json(['status' => $status, 'message' => $message] + $extra, $code);
    }

    /** Offline copy of operational records (full, or changes since ?since=). */
    public function snapshot(Request $request, OfflineSnapshotService $snapshots): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date']]);

        $since = $request->filled('since') ? Carbon::parse($request->input('since')) : null;

        return response()->json($snapshots->build($since))->header('Cache-Control', 'no-store, private');
    }
}
