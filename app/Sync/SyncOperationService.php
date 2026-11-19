<?php

namespace App\Sync;

use App\Exceptions\WorkflowException;
use App\Http\Requests\Buyers\BuyerRequest;
use App\Http\Requests\Dispatches\RespondDispatchRequest;
use App\Http\Requests\Dispatches\StoreDispatchRequest;
use App\Http\Requests\Loadings\LoadingPhotosRequest;
use App\Http\Requests\Loadings\LoadingRequest;
use App\Http\Requests\Mills\MillRequest;
use App\Http\Requests\Samples\BundleRequest;
use App\Http\Requests\Samples\SampleRequest;
use App\Http\Requests\Samples\SampleRules;
use App\Http\Requests\Varieties\RiceVarietyRequest;
use App\Models\Buyer;
use App\Models\Loading;
use App\Models\LoadingPhoto;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\SampleBundle;
use App\Models\SampleDispatch;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\DirectoryService;
use App\Services\DispatchService;
use App\Services\LoadingService;
use App\Services\SampleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Applies one operation queued by the offline app.
 *
 * Every operation is validated with the same Form Request as the matching
 * online form (including its authorize() / policy checks) and executed by the
 * same service, so offline changes follow exactly the same business rules.
 *
 * Exactly-once: the operation uuid is written to sync_operations inside the
 * same transaction as the change. A retry (e.g. the response was lost on a
 * weak connection) returns the stored result instead of applying it again.
 *
 * References may be server ids or uuids of records created offline.
 */
class SyncOperationService
{
    public const TYPES = [
        'mill.create', 'mill.update', 'buyer.create', 'buyer.update', 'variety.create',
        'sample.create', 'bundle.create', 'bundle.add_samples',
        'dispatch.create', 'dispatch.respond',
        'loading.create', 'loading.loaded', 'order.complete', 'photo.add',
    ];

    public function __construct(
        private readonly DirectoryService $directory,
        private readonly SampleService $samples,
        private readonly DispatchService $dispatches,
        private readonly LoadingService $loadings,
    ) {}

    /**
     * @return array{replayed: bool, records: list<array{type: string, record: array}>}
     */
    public function apply(array $op, User $user, ?UploadedFile $photo = null): array
    {
        if ($done = $this->previous($op['uuid'], $user)) {
            return $done;
        }

        try {
            return DB::transaction(function () use ($op, $user, $photo) {
                $log = SyncOperation::create(['uuid' => $op['uuid'], 'user_id' => $user->id, 'type' => $op['type']]);

                $models = $this->handle($op['type'], $op['payload'] ?? [], $user, $photo);
                $result = ['records' => array_map(fn (Model $m) => SyncPresenter::present($m->fresh()), $models)];

                $log->update(['result' => $result]);

                return ['replayed' => false] + $result;
            });
        } catch (UniqueConstraintViolationException) {
            // The same operation arrived twice at the same moment; the other request applied it.
            return $this->previous($op['uuid'], $user) ?? throw new WorkflowException('This change is already being processed. Try again shortly.');
        }
    }

    private function previous(string $uuid, User $user): ?array
    {
        $done = SyncOperation::find($uuid);

        if (! $done) {
            return null;
        }
        if ($done->user_id !== $user->id) {
            throw new WorkflowException('This change was recorded by a different account.');
        }

        return ['replayed' => true] + ($done->result ?? ['records' => []]);
    }

    /** @return list<Model> */
    private function handle(string $type, array $p, User $user, ?UploadedFile $photo): array
    {
        $fields = (array) ($p['fields'] ?? []);

        return match ($type) {
            'mill.create' => [$this->existing(Mill::class, $p) ?? $this->directory->createMill(
                $this->validated(MillRequest::class, $user, $fields)->validated(), $user, $this->uuid($p))],
            'mill.update' => [$this->updateDirectory(Mill::class, 'mill', MillRequest::class, $p, $user)],
            'buyer.create' => [$this->existing(Buyer::class, $p) ?? $this->directory->createBuyer(
                $this->validated(BuyerRequest::class, $user, $fields)->validated(), $user, $this->uuid($p))],
            'buyer.update' => [$this->updateDirectory(Buyer::class, 'buyer', BuyerRequest::class, $p, $user)],
            'variety.create' => [$this->existing(RiceVariety::class, $p) ?? $this->directory->createVariety(
                $this->validated(RiceVarietyRequest::class, $user, $fields)->validated(), $this->uuid($p))],
            'sample.create' => [$this->createSample($p, $user)],
            'bundle.create' => $this->createBundle($p, $user),
            'bundle.add_samples' => $this->addToBundle($p, $user),
            'dispatch.create' => $this->createDispatches($p, $user),
            'dispatch.respond' => [$this->respond($p, $user)],
            'loading.create' => $this->createLoading($p, $user),
            'loading.loaded' => [$this->markLoaded($p, $user)],
            'order.complete' => [$this->completeOrder($p, $user)],
            'photo.add' => [$this->addPhoto($p, $user, $photo)],
        };
    }

    // ---- directory -------------------------------------------------------

    private function updateDirectory(string $class, string $param, string $request, array $p, User $user): Model
    {
        $model = $this->find($class, $p['target'] ?? null, $param);
        $this->guardVersion($model, $p);
        $data = $this->validated($request, $user, (array) ($p['fields'] ?? []), [$param => $model])->validated();

        return $class === Mill::class ? $this->directory->updateMill($model, $data) : $this->directory->updateBuyer($model, $data);
    }

    // ---- samples & bundles ----------------------------------------------

    private function createSample(array $p, User $user): Sample
    {
        if ($existing = $this->existing(Sample::class, $p)) {
            return $existing;
        }

        $fields = $this->resolve((array) ($p['fields'] ?? []), [
            'mill_id' => [Mill::class, 'mill'], 'rice_variety_id' => [RiceVariety::class, 'rice variety'], 'sample_bundle_id' => [SampleBundle::class, 'bundle'],
        ]);
        $data = $this->validated(SampleRequest::class, $user, $fields)->validated();

        return $this->samples->create([...$data, 'uuid' => $this->uuid($p)], $user);
    }

    /** @return list<Model> */
    private function createBundle(array $p, User $user): array
    {
        if ($existing = $this->existing(SampleBundle::class, $p)) {
            return [$existing, ...$existing->samples];
        }

        [$fields, $uuids] = $this->bundleRows((array) ($p['fields'] ?? []));
        $request = $this->validated(BundleRequest::class, $user, $fields);
        $bundle = $this->samples->createBundle([...$request->validated(), 'uuid' => $this->uuid($p)], $this->withUuids($request->rows(), $uuids), $user);

        return [$bundle, ...$bundle->samples()->get()];
    }

    /** @return list<Model> */
    private function addToBundle(array $p, User $user): array
    {
        $bundle = $this->find(SampleBundle::class, $p['target'] ?? null, 'bundle');
        [$fields, $uuids] = $this->bundleRows((array) ($p['fields'] ?? []));

        if ($uuids && Sample::whereIn('uuid', array_values($uuids))->exists()) {
            return [$bundle, ...Sample::whereIn('uuid', array_values($uuids))->get()];
        }

        $request = $this->validated(BundleRequest::class, $user, $fields, ['bundle' => $bundle]);

        return [$bundle, ...$this->samples->addToBundle($bundle, $this->withUuids($request->rows(), $uuids), $user)];
    }

    /** Resolve mill/variety references inside bundle rows; return [fields, rowKey => sample uuid]. */
    private function bundleRows(array $fields): array
    {
        $uuids = [];
        $rows = [];

        foreach ((array) ($fields['samples'] ?? []) as $key => $row) {
            $row = (array) $row;
            if (! empty($row['uuid'])) {
                $uuids[$key] = $this->assertUuid($row['uuid']);
            }
            unset($row['uuid']);
            $rows[$key] = $this->resolve($row, ['mill_id' => [Mill::class, 'mill'], 'rice_variety_id' => [RiceVariety::class, 'rice variety']]);
        }

        return [['samples' => $rows] + $fields, $uuids];
    }

    private function withUuids(array $rows, array $uuids): array
    {
        foreach ($rows as $key => $row) {
            $rows[$key]['uuid'] = $uuids[$key] ?? null;
        }

        return $rows;
    }

    // ---- dispatches & responses -----------------------------------------

    /** @return list<Model> */
    private function createDispatches(array $p, User $user): array
    {
        $pairs = collect((array) ($p['pairs'] ?? []))->map(fn ($pair) => [
            'uuid' => $this->assertUuid($pair['uuid'] ?? null),
            'sample_id' => $this->id(Sample::class, $pair['sample'] ?? null, 'sample'),
            'buyer_id' => $this->id(Buyer::class, $pair['buyer'] ?? null, 'buyer'),
        ]);

        $already = SampleDispatch::whereIn('uuid', $pairs->pluck('uuid'))->get();
        if ($already->isNotEmpty()) {
            return $already->all();
        }

        $fields = (array) ($p['fields'] ?? []);
        $fields['sample_ids'] = $pairs->pluck('sample_id')->unique()->values()->all();
        $fields['buyer_ids'] = $pairs->pluck('buyer_id')->unique()->values()->all();

        $data = $this->validated(StoreDispatchRequest::class, $user, $fields)->validated();
        $uuids = $pairs->mapWithKeys(fn ($pair) => ["{$pair['sample_id']}:{$pair['buyer_id']}" => $pair['uuid']])->all();

        return $this->dispatches->dispatch($data['sample_ids'], $data['buyer_ids'], $data['dispatch_date'], $data['notes'] ?? null, $user, $uuids)->all();
    }

    private function respond(array $p, User $user): SampleDispatch
    {
        $dispatch = $this->find(SampleDispatch::class, $p['target'] ?? null, 'dispatch');
        $this->guardVersion($dispatch, $p);
        $data = $this->validated(RespondDispatchRequest::class, $user, (array) ($p['fields'] ?? []), ['dispatch' => $dispatch])->validated();

        // "Apply mine" after a conflict: replace the server's answer, if that is still allowed.
        if (! empty($p['force']) && ! $dispatch->isPending() && $dispatch->status->value !== $data['status']) {
            $this->dispatches->reopen($dispatch, $user);
            $dispatch->refresh();
        }

        return $this->dispatches->respond($dispatch, $data, $user);
    }

    // ---- loading ---------------------------------------------------------

    /** @return list<Model> */
    private function createLoading(array $p, User $user): array
    {
        if ($existing = $this->existing(Loading::class, $p)) {
            return [$existing, $existing->dispatch];
        }

        $dispatch = $this->find(SampleDispatch::class, $p['target'] ?? null, 'confirmed order');
        $request = $this->validated(LoadingRequest::class, $user, (array) ($p['fields'] ?? []), ['dispatch' => $dispatch]);
        $action = $request->input('action', 'save');

        $loading = $this->loadings->create(
            $dispatch, $request->fields(), [], $user,
            markLoaded: in_array($action, ['loaded', 'complete'], true),
            completeOrder: $action === 'complete',
            uuid: $this->uuid($p),
        );

        return [$loading, $dispatch];
    }

    private function markLoaded(array $p, User $user): Loading
    {
        $loading = $this->find(Loading::class, $p['target'] ?? null, 'truck loading');

        if (! $loading->isCompleted()) {
            Gate::forUser($user)->authorize('markLoaded', $loading);
            $this->loadings->markLoaded($loading, $user);
        }

        return $loading;
    }

    private function completeOrder(array $p, User $user): SampleDispatch
    {
        $dispatch = $this->find(SampleDispatch::class, $p['target'] ?? null, 'order');

        if (! $dispatch->isLoadingCompleted()) {
            Gate::forUser($user)->authorize('completeLoading', $dispatch);
            $this->loadings->completeOrder($dispatch, $user);
        }

        return $dispatch;
    }

    // ---- photos ----------------------------------------------------------

    private function addPhoto(array $p, User $user, ?UploadedFile $photo): Model
    {
        $uuid = $this->uuid($p);

        if (($p['target_type'] ?? null) === 'loading') {
            $loading = $this->find(Loading::class, $p['target'] ?? null, 'truck loading');
            if (! LoadingPhoto::where('uuid', $uuid)->exists()) {
                $this->validated(LoadingPhotosRequest::class, $user, [], ['loading' => $loading], ['photos' => [$photo]]);
                $this->loadings->addPhotos($loading, [$photo], $user, [$uuid]);
            }

            return $loading;
        }

        $sample = $this->find(Sample::class, $p['target'] ?? null, 'sample');
        Gate::forUser($user)->authorize('update', $sample);
        Validator::make(['photo' => $photo], ['photo' => ['required', ...array_slice(SampleRules::photo(), 1)]])->validate();

        return $this->samples->update($sample, ['photo' => $photo]);
    }

    // ---- helpers ---------------------------------------------------------

    /**
     * Validate data with an existing Form Request exactly as if it came from
     * the online form: prepareForValidation, authorize(), rules, after hooks.
     */
    private function validated(string $class, User $user, array $data, array $routeParams = [], array $files = []): FormRequest
    {
        /** @var FormRequest $request */
        $request = $class::create('/sync/operations', 'POST', $data, [], $files, ['HTTP_ACCEPT' => 'application/json']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        $route = (new Route('POST', 'sync/operations', []))->bind($request);
        foreach ($routeParams as $name => $value) {
            $route->setParameter($name, $value);
        }
        $request->setRouteResolver(fn () => $route);

        $request->validateResolved();

        return $request;
    }

    /** A create op whose record already exists (retry after a lost response) is already done. */
    private function existing(string $class, array $p): ?Model
    {
        return $class::query()->where('uuid', $this->uuid($p))->first();
    }

    private function guardVersion(Model $model, array $p): void
    {
        if (empty($p['force']) && isset($p['base_version']) && (int) $p['base_version'] !== (int) $model->version) {
            throw new SyncConflict($model);
        }
    }

    /** @param  array<string, array{class-string<Model>, string}>  $map */
    private function resolve(array $fields, array $map): array
    {
        foreach ($map as $field => [$class, $what]) {
            if (array_key_exists($field, $fields) && $fields[$field] !== null && $fields[$field] !== '') {
                $fields[$field] = $this->id($class, $fields[$field], $what);
            }
        }

        return $fields;
    }

    private function id(string $class, mixed $ref, string $what): int
    {
        if (is_int($ref) || (is_string($ref) && ctype_digit($ref))) {
            return (int) $ref; // server id; the form request checks it exists
        }

        return $this->find($class, $ref, $what)->getKey();
    }

    private function find(string $class, mixed $ref, string $what): Model
    {
        $model = match (true) {
            is_int($ref), is_string($ref) && ctype_digit($ref) => $class::find((int) $ref),
            is_string($ref) && Str::isUuid($ref) => $class::where('uuid', $ref)->first(),
            default => throw ValidationException::withMessages(['reference' => "Invalid {$what} reference."]),
        };

        return $model ?? throw new MissingReference((string) $ref, $what);
    }

    private function uuid(array $p): string
    {
        return $this->assertUuid($p['uuid'] ?? null);
    }

    private function assertUuid(mixed $value): string
    {
        if (! is_string($value) || ! Str::isUuid($value)) {
            throw ValidationException::withMessages(['uuid' => 'Invalid record identifier.']);
        }

        return strtolower($value);
    }
}
