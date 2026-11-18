<?php

namespace App\Http\Requests\Dispatches;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->route('dispatch'));
    }

    public function rules(): array
    {
        $dispatchDate = $this->route('dispatch')->dispatch_date->format('Y-m-d');
        $confirmed = fn () => $this->input('status') === 'confirmed';
        $rejected = fn () => $this->input('status') === 'rejected';

        return [
            'status' => ['required', Rule::in(['confirmed', 'rejected'])],

            'confirmed_quantity' => [Rule::requiredIf($confirmed), 'nullable', 'numeric', 'gt:0', 'max:9999999999'],
            'confirmed_quantity_unit' => [Rule::requiredIf($confirmed), 'nullable', Rule::in(array_keys(config('rice.quantity_units')))],
            'rate' => [Rule::requiredIf($confirmed), 'nullable', 'numeric', 'gt:0', 'max:9999999999'],
            'rate_unit' => [Rule::requiredIf($confirmed), 'nullable', Rule::in(array_keys(config('rice.rate_units')))],
            'packing_type' => [Rule::requiredIf($confirmed), 'nullable', Rule::in(array_keys(config('rice.packing_types')))],
            'packing_details' => ['nullable', 'string', 'max:2000'],
            'confirmation_date' => [Rule::requiredIf($confirmed), 'nullable', 'date', 'after_or_equal:'.$dispatchDate, 'before_or_equal:today'],

            'rejection_reason' => [Rule::requiredIf($rejected), 'nullable', 'string', 'max:2000'],
            'rejection_date' => [Rule::requiredIf($rejected), 'nullable', 'date', 'after_or_equal:'.$dispatchDate, 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Choose whether the buyer confirmed or rejected the sample.',
            'confirmation_date.after_or_equal' => 'The confirmation date cannot be before the dispatch date.',
            'rejection_date.after_or_equal' => 'The rejection date cannot be before the dispatch date.',
        ];
    }

    public function attributes(): array
    {
        return [
            'confirmed_quantity' => 'confirmed quantity', 'confirmed_quantity_unit' => 'quantity unit',
            'rate_unit' => 'rate unit', 'packing_type' => 'packing type',
        ];
    }
}
