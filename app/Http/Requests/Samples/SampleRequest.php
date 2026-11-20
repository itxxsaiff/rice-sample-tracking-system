<?php

namespace App\Http\Requests\Samples;

use App\Models\Sample;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One sample: used by the edit form and by samples created offline
 * (each offline sample is synced on its own). Sample numbers are always
 * assigned automatically.
 */
class SampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sample = $this->route('sample');

        return $sample ? $this->user()->can('update', $sample) : $this->user()->can('create', Sample::class);
    }

    public function rules(): array
    {
        return [
            'collection_date' => ['required', 'date', 'before_or_equal:today'],
            ...SampleRules::fields(currentVarietyId: $this->route('sample')?->rice_variety_id),
        ];
    }

    public function messages(): array
    {
        return ['collection_date.before_or_equal' => 'The collection date cannot be in the future.'];
    }

    public function attributes(): array
    {
        return ['mill_id' => 'mill', 'rice_variety_id' => 'variety'];
    }
}
