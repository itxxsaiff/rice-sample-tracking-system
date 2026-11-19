<?php

namespace App\Services;

use App\Models\Sample;
use App\Models\SampleBundle;
use App\Models\User;
use App\Support\PhotoStorage;
use App\Support\ReferenceNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class SampleService
{
    /** Paths written during the current operation, removed again if it fails. */
    private array $storedPaths = [];

    public function create(array $data, User $user, ?SampleBundle $bundle = null): Sample
    {
        return $this->guarded(fn () => DB::transaction(function () use ($data, $user, $bundle) {
            $sample = $this->makeSample($data, $user, $bundle ?? $this->bundleFromData($data));
            $sample->recordActivity('created', "Recorded sample {$sample->sample_number} from {$sample->mill->name}");

            return $sample;
        }));
    }

    /**
     * Create a bundle and all of its samples atomically. Each row keeps its own mill.
     *
     * @param  array<int, array>  $rows
     */
    public function createBundle(array $bundleData, array $rows, User $user): SampleBundle
    {
        return $this->guarded(fn () => DB::transaction(function () use ($bundleData, $rows, $user) {
            $bundle = new SampleBundle([
                'bundle_number' => $bundleData['bundle_number'] ?? null ?: ReferenceNumber::next('bundle', SampleBundle::class, 'bundle_number'),
                'bundle_date' => $bundleData['bundle_date'],
                'notes' => $bundleData['notes'] ?? null,
            ]);
            if (! empty($bundleData['uuid'])) {
                $bundle->uuid = $bundleData['uuid'];
            }
            $bundle->created_by = $user->id;
            $bundle->save();

            $samples = $this->createRows($bundle, $rows, $user);
            $bundle->recordActivity('created', "Created bundle {$bundle->bundle_number} with {$samples->count()} sample(s)");

            return $bundle;
        }));
    }

    /** Add more samples to an existing bundle. */
    public function addToBundle(SampleBundle $bundle, array $rows, User $user): Collection
    {
        return $this->guarded(fn () => DB::transaction(function () use ($bundle, $rows, $user) {
            $samples = $this->createRows($bundle, $rows, $user);
            $bundle->recordActivity('updated', "Added {$samples->count()} sample(s) to bundle {$bundle->bundle_number}");

            return $samples;
        }));
    }

    public function update(Sample $sample, array $data, bool $removePhoto = false): Sample
    {
        $oldPhoto = $sample->photo_path;
        $newPhoto = $data['photo'] ?? null;

        $sample = $this->guarded(fn () => DB::transaction(function () use ($sample, $data, $newPhoto, $removePhoto) {
            $sample->fill(collect($data)->only(['sample_number', 'mill_id', 'rice_variety_id', 'collection_date', 'quantity', 'quantity_unit', 'notes', 'sample_bundle_id'])->all());

            if ($newPhoto instanceof UploadedFile) {
                $sample->photo_path = $this->storePhoto($newPhoto);
            } elseif ($removePhoto) {
                $sample->photo_path = null;
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

    private function createRows(SampleBundle $bundle, array $rows, User $user): Collection
    {
        return collect($rows)->values()->map(function (array $row) use ($bundle, $user) {
            $row['collection_date'] = $row['collection_date'] ?? null ?: $bundle->bundle_date->format('Y-m-d');

            return $this->makeSample($row, $user, $bundle);
        });
    }

    private function makeSample(array $data, User $user, ?SampleBundle $bundle): Sample
    {
        $sample = new Sample(collect($data)->only(['mill_id', 'rice_variety_id', 'collection_date', 'quantity', 'quantity_unit', 'notes'])->all());
        $sample->sample_number = ($data['sample_number'] ?? null) ?: ReferenceNumber::next('sample', Sample::class, 'sample_number');
        if (! empty($data['uuid'])) {
            $sample->uuid = $data['uuid']; // assigned on the phone when created offline
        }
        $sample->sample_bundle_id = $bundle?->id;
        $sample->created_by = $user->id;

        if ($sample->quantity === null) {
            $sample->quantity_unit = null;
        }

        if (($data['photo'] ?? null) instanceof UploadedFile) {
            $sample->photo_path = $this->storePhoto($data['photo']);
        }

        $sample->save();

        return $sample;
    }

    private function bundleFromData(array $data): ?SampleBundle
    {
        return empty($data['sample_bundle_id']) ? null : SampleBundle::find($data['sample_bundle_id']);
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
