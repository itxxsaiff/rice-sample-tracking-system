<?php

namespace App\Services;

use App\Enums\LoadingStatus;
use App\Enums\TruckLoadingStatus;
use App\Exceptions\WorkflowException;
use App\Models\Loading;
use App\Models\LoadingPhoto;
use App\Models\SampleDispatch;
use App\Models\User;
use App\Support\PhotoStorage;
use App\Support\ReferenceNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Loading workflow for confirmed orders:
 *   order (dispatch) loading_status: pending → in_progress → completed
 *   each truck (loading) status:     pending → completed
 * An order can only be completed once it has at least one truck and every
 * truck is marked loaded.
 */
class LoadingService
{
    private array $storedPaths = [];

    /** @param  array<int, UploadedFile>  $photos */
    public function create(SampleDispatch $dispatch, array $data, array $photos, User $user, bool $markLoaded = false, bool $completeOrder = false, ?string $uuid = null): Loading
    {
        return $this->guarded(fn () => DB::transaction(function () use ($dispatch, $data, $photos, $user, $markLoaded, $completeOrder, $uuid) {
            $dispatch = SampleDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);

            if (! $dispatch->isConfirmed()) {
                throw new WorkflowException('Only confirmed dispatches can be loaded. Rejected or pending dispatches do not enter loading.');
            }
            if ($dispatch->isLoadingCompleted()) {
                throw new WorkflowException('This order is already marked as completed.');
            }

            $loading = new Loading($data);
            $loading->loading_number = ReferenceNumber::next('loading', Loading::class, 'loading_number');
            if ($uuid) {
                $loading->uuid = $uuid;
            }
            $loading->sample_dispatch_id = $dispatch->id;
            $loading->created_by = $user->id;
            $loading->status = TruckLoadingStatus::Pending;
            $loading->save();

            $this->attachPhotos($loading, $photos, $user);

            $dispatch->loading_status = LoadingStatus::InProgress;
            $dispatch->save();

            $loading->recordActivity('loading_recorded', "Truck {$loading->truck_number} recorded with {$loading->quantityLabel()}");
            $dispatch->recordActivity('loading_recorded', "Truck {$loading->truck_number} ({$loading->loading_number}): {$loading->quantityLabel()}");

            if ($markLoaded) {
                $this->markLoaded($loading, $user);
            }
            if ($completeOrder) {
                $this->completeOrder($dispatch, $user);
            }

            return $loading;
        }));
    }

    public function update(Loading $loading, array $data, array $photos, User $user): Loading
    {
        return $this->guarded(fn () => DB::transaction(function () use ($loading, $data, $photos, $user) {
            $loading->fill($data);
            $changes = array_keys($loading->getDirty());
            $loading->save();
            $this->attachPhotos($loading, $photos, $user);

            if ($changes) {
                $loading->recordActivity('updated', "Updated truck loading {$loading->loading_number}", ['fields' => $changes]);
            }

            return $loading;
        }));
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @param  array<int, string>  $uuids  optional per-photo uuids (offline uploads), same order as $photos
     */
    public function addPhotos(Loading $loading, array $photos, User $user, array $uuids = []): int
    {
        return $this->guarded(fn () => DB::transaction(function () use ($loading, $photos, $user, $uuids) {
            $count = $this->attachPhotos($loading, $photos, $user, $uuids);
            $loading->recordActivity('photos_added', "Added {$count} photo(s) to {$loading->loading_number}");

            return $count;
        }));
    }

    public function deletePhoto(LoadingPhoto $photo): void
    {
        $path = $photo->path;
        $photo->delete();
        PhotoStorage::delete($path);
    }

    public function markLoaded(Loading $loading, User $user): Loading
    {
        if ($loading->isCompleted()) {
            throw new WorkflowException('This truck is already marked as loaded.');
        }

        $loading->status = TruckLoadingStatus::Completed;
        $loading->completed_at = now();
        $loading->completed_by = $user->id;
        $loading->save();

        $loading->recordActivity('loading_completed', "Truck {$loading->truck_number} marked as loaded");

        return $loading;
    }

    public function completeOrder(SampleDispatch $dispatch, User $user): SampleDispatch
    {
        return DB::transaction(function () use ($dispatch) {
            $dispatch = SampleDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $loadings = $dispatch->loadings()->get();

            if (! $dispatch->isConfirmed() || $dispatch->isLoadingCompleted()) {
                throw new WorkflowException('Only an open confirmed order can be completed.');
            }
            if ($loadings->isEmpty()) {
                throw new WorkflowException('Record at least one truck loading before completing the order.');
            }
            if ($loadings->contains(fn (Loading $l) => ! $l->isCompleted())) {
                throw new WorkflowException('Mark every truck as loaded before completing the order.');
            }

            $dispatch->loading_status = LoadingStatus::Completed;
            $dispatch->loading_completed_at = now();
            $dispatch->save();
            $dispatch->setRelation('loadings', $loadings);

            $dispatch->recordActivity('order_completed', "Order completed: {$loadings->count()} truck(s), ".collect($dispatch->loadedTotals())->map(fn ($q, $u) => SampleDispatch::formatQuantity($q, $u))->join(' + ').' loaded');

            return $dispatch;
        });
    }

    public function reopenOrder(SampleDispatch $dispatch): SampleDispatch
    {
        if (! $dispatch->isLoadingCompleted()) {
            throw new WorkflowException('This order is not completed.');
        }

        $dispatch->loading_status = LoadingStatus::InProgress;
        $dispatch->loading_completed_at = null;
        $dispatch->save();
        $dispatch->recordActivity('reopened', 'Completed order reopened for more loading');

        return $dispatch;
    }

    public function delete(Loading $loading): void
    {
        DB::transaction(function () use ($loading) {
            $paths = $loading->photos()->pluck('path');
            $dispatch = $loading->dispatch;
            $loading->delete();

            if (! $dispatch->loadings()->exists()) {
                $dispatch->loading_status = LoadingStatus::Pending;
                $dispatch->save();
            }
            $dispatch->recordActivity('updated', "Removed truck loading {$loading->loading_number} ({$loading->truck_number})");

            DB::afterCommit(fn () => $paths->each(fn ($p) => PhotoStorage::delete($p)));
        });
    }

    /** @param  array<int, UploadedFile>  $photos */
    private function attachPhotos(Loading $loading, array $photos, User $user, array $uuids = []): int
    {
        $count = 0;

        foreach (array_values($photos) as $i => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $this->storedPaths[] = PhotoStorage::store($file, 'loadings/'.$loading->id);
            $photo = new LoadingPhoto([
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
            $photo->uploaded_by = $user->id;
            if (! empty($uuids[$i])) {
                $photo->uuid = $uuids[$i];
            }
            $loading->photos()->save($photo);
            $count++;
        }

        return $count;
    }

    private function guarded(callable $operation): mixed
    {
        $this->storedPaths = [];

        try {
            return $operation();
        } catch (Throwable $e) {
            array_map([PhotoStorage::class, 'delete'], $this->storedPaths);

            throw $e;
        } finally {
            $this->storedPaths = [];
        }
    }
}
