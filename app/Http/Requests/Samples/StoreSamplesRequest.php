<?php

namespace App\Http\Requests\Samples;

use App\Models\Sample;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the "Add samples" form: one or more independent samples
 * (samples[key][field]) saved together. The samples are not linked to
 * each other; each gets its own automatic sample number.
 */
class StoreSamplesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Sample::class);
    }

    public function rules(): array
    {
        return [
            'samples' => ['required', 'array', 'min:1', 'max:50'],
            'samples.*' => ['array'],
            'samples.*.collection_date' => ['required', 'date', 'before_or_equal:today'],
            ...SampleRules::fields('samples.*.'),
        ];
    }

    public function messages(): array
    {
        return [
            'samples.required' => 'Add at least one sample.',
            'samples.*.mill_id.required' => 'Choose the mill this sample came from.',
            'samples.*.rice_variety_id.required' => 'Choose the rice variety.',
            'samples.*.collection_date.before_or_equal' => 'The collection date cannot be in the future.',
        ];
    }

    public function attributes(): array
    {
        return [
            'samples.*.mill_id' => 'mill', 'samples.*.rice_variety_id' => 'variety',
            'samples.*.quantity' => 'quantity', 'samples.*.quantity_unit' => 'unit',
            'samples.*.collection_date' => 'collection date', 'samples.*.notes' => 'notes',
        ];
    }

    /** @return list<array> the sample rows in the order they were entered */
    public function rows(): array
    {
        return array_values($this->validated('samples'));
    }
}
