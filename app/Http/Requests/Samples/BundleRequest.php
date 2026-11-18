<?php

namespace App\Http\Requests\Samples;

use App\Models\SampleBundle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a bundle header plus N sample rows (samples[key][field]).
 * Used both for creating a bundle and for adding rows to an existing one.
 */
class BundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bundle = $this->route('bundle');

        return $bundle ? $this->user()->can('update', $bundle) : $this->user()->can('create', SampleBundle::class);
    }

    protected function prepareForValidation(): void
    {
        $samples = collect($this->input('samples', []))->map(function ($row) {
            if (is_array($row) && isset($row['sample_number']) && is_string($row['sample_number'])) {
                $row['sample_number'] = strtoupper(trim($row['sample_number']));
            }

            return $row;
        })->all();

        $this->merge([
            'samples' => $samples,
            'bundle_number' => is_string($this->bundle_number) ? strtoupper(trim($this->bundle_number)) : $this->bundle_number,
        ]);
    }

    public function rules(): array
    {
        $header = $this->route('bundle') ? [] : [
            'bundle_number' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\/_. ]*$/', Rule::unique('sample_bundles', 'bundle_number')],
            'bundle_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        return $header + [
            'samples' => ['required', 'array', 'min:1', 'max:50'],
            'samples.*' => ['array'],
            'samples.*.sample_number' => [...SampleRules::sampleNumber(), 'distinct:ignore_case'],
            'samples.*.collection_date' => ['nullable', 'date', 'before_or_equal:today'],
            ...SampleRules::fields('samples.*.'),
        ];
    }

    public function messages(): array
    {
        return [
            'samples.required' => 'Add at least one sample to the bundle.',
            'samples.*.mill_id.required' => 'Choose the mill this sample came from.',
            'samples.*.rice_variety_id.required' => 'Choose the rice variety.',
            'samples.*.sample_number.distinct' => 'This sample number is used twice in the bundle.',
            'samples.*.sample_number.unique' => 'This sample number already exists.',
            'samples.*.photo.mimes' => 'The photo must be a JPG, PNG, WEBP or HEIC image.',
            'samples.*.photo.max' => 'The photo is too large.',
        ];
    }

    public function attributes(): array
    {
        return [
            'samples.*.mill_id' => 'mill', 'samples.*.rice_variety_id' => 'variety',
            'samples.*.quantity' => 'quantity', 'samples.*.quantity_unit' => 'unit',
            'samples.*.collection_date' => 'collection date', 'samples.*.sample_number' => 'sample number',
        ];
    }

    /** Sample rows with their uploaded photo files merged in. */
    public function rows(): array
    {
        $files = $this->file('samples', []);

        return collect($this->validated('samples'))
            ->map(fn (array $row, $key) => [...$row, 'photo' => $files[$key]['photo'] ?? null])
            ->all();
    }
}
