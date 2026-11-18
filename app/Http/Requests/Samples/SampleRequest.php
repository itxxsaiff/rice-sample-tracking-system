<?php

namespace App\Http\Requests\Samples;

use App\Models\Sample;
use Illuminate\Foundation\Http\FormRequest;

class SampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sample = $this->route('sample');

        return $sample ? $this->user()->can('update', $sample) : $this->user()->can('create', Sample::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['sample_number' => is_string($this->sample_number) ? strtoupper(trim($this->sample_number)) : $this->sample_number]);
    }

    public function rules(): array
    {
        return [
            'sample_number' => SampleRules::sampleNumber($this->route('sample')?->id),
            'collection_date' => ['required', 'date', 'before_or_equal:today'],
            'sample_bundle_id' => ['nullable', 'integer', 'exists:sample_bundles,id'],
            'remove_photo' => ['sometimes', 'boolean'],
            ...SampleRules::fields(),
        ];
    }

    public function messages(): array
    {
        return SampleRules::messages() + ['collection_date.before_or_equal' => 'The collection date cannot be in the future.'];
    }

    public function attributes(): array
    {
        return ['mill_id' => 'mill', 'rice_variety_id' => 'variety', 'sample_bundle_id' => 'bundle'];
    }
}
