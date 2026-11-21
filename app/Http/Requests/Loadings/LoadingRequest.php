<?php

namespace App\Http\Requests\Loadings;

use App\Http\Requests\Samples\SampleRules;
use App\Models\SampleDispatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $loading = $this->route('loading');

        return $loading
            ? $this->user()->can('update', $loading)
            : $this->user()->can('recordLoading', $this->dispatchModel());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->truck_number)) {
            $this->merge(['truck_number' => strtoupper(preg_replace('/\s+/', ' ', trim($this->truck_number)))]);
        }
    }

    public function rules(): array
    {
        $earliest = $this->dispatchModel()->confirmation_date?->format('Y-m-d');

        return [
            'truck_number' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9 \-\/]*$/'],
            'loading_date' => array_filter(['required', 'date', 'before_or_equal:today', $earliest ? 'after_or_equal:'.$earliest : null]),
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            // Unit and receiver are automatic (the order's unit, the buyer); only older offline changes send them.
            'quantity_unit' => ['nullable', Rule::in(array_keys(config('rice.quantity_units')))],
            'bag_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'packing_type' => ['nullable', Rule::in(array_keys(config('rice.packing_types')))],
            'packing_details' => ['nullable', 'string', 'max:2000'],
            'receiver_name' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.config('rice.max_photos_per_upload')],
            'photos.*' => SampleRules::photo(),
            'action' => ['nullable', Rule::in(['save', 'loaded', 'complete'])],
        ];
    }

    public function messages(): array
    {
        return [
            'truck_number.regex' => 'Use letters, numbers, spaces and dashes only (e.g. TLE-1234).',
            'loading_date.after_or_equal' => 'The loading date cannot be before the order was confirmed.',
            'photos.max' => 'You can upload up to :max photos at a time.',
            'photos.*.mimes' => 'Photos must be JPG, PNG, WEBP or HEIC images.',
            'photos.*.max' => 'One of the photos is too large.',
        ];
    }

    public function fields(): array
    {
        return collect($this->validated())->except(['photos', 'action'])->all();
    }

    public function photos(): array
    {
        return $this->file('photos', []);
    }

    private function dispatchModel(): SampleDispatch
    {
        return $this->route('dispatch') ?? $this->route('loading')->dispatch;
    }
}
