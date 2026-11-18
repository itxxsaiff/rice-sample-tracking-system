<?php

namespace App\Http\Requests\Dispatches;

use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Services\DispatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SampleDispatch::class);
    }

    public function rules(): array
    {
        return [
            'sample_ids' => ['required', 'array', 'min:1', 'max:50'],
            'sample_ids.*' => ['integer', 'distinct', 'exists:samples,id'],
            'buyer_ids' => ['required', 'array', 'min:1', 'max:50'],
            'buyer_ids.*' => ['integer', 'distinct', 'exists:buyers,id'],
            'dispatch_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sample_ids.required' => 'Choose at least one sample to send.',
            'buyer_ids.required' => 'Choose at least one buyer.',
            'dispatch_date.before_or_equal' => 'The dispatch date cannot be in the future.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $sampleIds = $this->input('sample_ids');
                $buyerIds = $this->input('buyer_ids');

                if ($duplicates = app(DispatchService::class)->activeDuplicates($sampleIds, $buyerIds)) {
                    $validator->errors()->add('buyer_ids', 'Already sent and awaiting a response or confirmed: '.implode(', ', $duplicates).'. Untick those buyers or samples.');
                }

                $tooEarly = Sample::whereIn('id', $sampleIds)->whereDate('collection_date', '>', $this->input('dispatch_date'))->pluck('sample_number');
                if ($tooEarly->isNotEmpty()) {
                    $validator->errors()->add('dispatch_date', 'The dispatch date is before the collection date of '.$tooEarly->join(', ').'.');
                }
            },
        ];
    }
}
