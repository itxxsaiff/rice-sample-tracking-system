<?php

namespace App\Services;

use App\Models\Sample;
use App\Models\User;
use App\Support\PhotoStorage;
use App\Support\ReferenceNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class SampleService
{
    private const FIELDS = ['mill_id', 'rice_variety_id', 'collection_date', 'quantity', 'quantity_unit', 'notes'];

    /** Paths written during the current operation, removed again if it fails. */
    private array $storedPaths = [];

    public function create(array $data, User $user): Sample
    {
        return DB::transaction(fn () => $this->makeSample($data, $user));
    }

    /**
     * Save several independent samples at once (the "Add another sample"
     * rows of one form). All are saved, or none if one fails.
     *
     * @param  list<array>  $rows
     * @return Collection<int, Sample>
     */
    public function createMany(array $rows, User $user): Collection
    {
        return DB::transaction(fn () => collect($rows)->values()->map(fn (array $row) => $this->makeSample($row, $user)));
    }

    public function update(Sample $sample, array $data): Sample
    {
        $oldPhoto = $sample->photo_path;
        $newPhoto = $data['photo'] ?? null;

        $sample = $this->guarded(fn () => DB::transaction(function () use ($sample, $data, $newPhoto) {
            $sample->fill(collect($data)->only(self::FIELDS)->all());

            // Only photos queued on a phone before sample photos were removed still arrive here.
            if ($newPhoto instanceof UploadedFile) {
                $sample->photo_path = $this->storePhoto($newPhoto);
            }

            if ($sample->quantity === null) {
                $sample->quantity_unit = null;
            }

            $changes = array_keys($sample->getDirty());
            $sample->save();

            if ($changes) {
                $sample->recordActivity('updated', "Updated sample {$sample->sample_number}", ['fields' => $changes]);
            }

            return $sample;
        }));

        // Only remove the old file once the database change has committed.
        if ($oldPhoto && $oldPhoto !== $sample->photo_path) {
            PhotoStorage::delete($oldPhoto);
        }

        return $sample;
    }

    public function delete(Sample $sample): void
    {
        $path = $sample->photo_path;
        $sample->delete();
        PhotoStorage::delete($path);
    }

    private function makeSample(array $data, User $user): Sample
    {
        $sample = new Sample(collect($data)->only(self::FIELDS)->all());
        $sample->sample_number = ReferenceNumber::next('sample', Sample::class, 'sample_number');
        if (! empty($data['uuid'])) {
            $sample->uuid = $data['uuid']; // assigned on the phone when created offline
        }
        $sample->created_by = $user->id;

        // The amount collected is always the same (2 kg); only older offline changes may carry their own.
        if ($sample->quantity === null) {
            $sample->quantity = config('rice.sample_default.quantity');
            $sample->quantity_unit = config('rice.sample_default.unit');
        }

        $sample->save();
        $sample->recordActivity('created', "Recorded sample {$sample->sample_number} from {$sample->mill->name}");

        return $sample;
    }

    private function storePhoto(UploadedFile $file): string
    {
        return $this->storedPaths[] = PhotoStorage::store($file, 'samples');
    }

    /** Run an operation; if it throws, delete any photos it already wrote. */
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
