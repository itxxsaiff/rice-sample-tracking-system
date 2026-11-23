<?php

namespace App\Http\Requests\Dispatches;

use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Services\DispatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The "Dispatch Samples" form: one or more dispatches (dispatches[key][…]),
 * each with its own samples, buyers, date and notes, saved together.
 * Every sample–buyer pair becomes its own dispatch record.
 */
class StoreDispatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SampleDispatch::class);
    }

    /** A form opened before "Add another dispatch" existed sends one flat dispatch. */
    protected function prepareForValidation(): void
    {
        if (! $this->has('dispatches') && $this->hasAny(['sample_ids', 'buyer_ids'])) {
            $this->merge(['dispatches' => [$this->only(['sample_ids', 'buyer_ids', 'dispatch_date', 'notes'])]]);
        }
    }

    public function rules(): array
    {
        return [
            'dispatches' => ['required', 'array', 'min:1', 'max:20'],
            'dispatches.*' => ['array'],
            'dispatches.*.sample_ids' => ['required', 'array', 'min:1', 'max:50'],
            'dispatches.*.sample_ids.*' => ['integer', 'exists:samples,id'],
            'dispatches.*.buyer_ids' => ['required', 'array', 'min:1', 'max:50'],
            'dispatches.*.buyer_ids.*' => ['integer', 'exists:buyers,id'],
            'dispatches.*.dispatch_date' => ['required', 'date', 'before_or_equal:today'],
            'dispatches.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'dispatches.required' => 'Add at least one dispatch.',
            'dispatches.*.sample_ids.required' => 'Choose at least one sample to send.',
            'dispatches.*.buyer_ids.required' => 'Choose at least one buyer.',
            'dispatches.*.dispatch_date.before_or_equal' => 'The dispatch date cannot be in the future.',
        ];
    }

    public function attributes(): array
    {
        return ['dispatches.*.dispatch_date' => 'dispatch date', 'dispatches.*.notes' => 'notes'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $service = app(DispatchService::class);
                $seen = [];

                foreach ($this->groups() as $key => $group) {
                    if ($duplicates = $service->activeDuplicates($group['sample_ids'], $group['buyer_ids'])) {
                        $validator->errors()->add("dispatches.$key.buyer_ids", 'Already sent and awaiting a response or confirmed: '.implode(', ', $duplicates).'. Untick those buyers or samples.');
                    }

                    // The same sample to the same buyer twice in one go.
                    $repeated = [];
                    foreach ($group['sample_ids'] as $sampleId) {
                        foreach ($group['buyer_ids'] as $buyerId) {
                            if (isset($seen["$sampleId:$buyerId"])) {
                                $repeated[] = $sampleId;
                            }
                            $seen["$sampleId:$buyerId"] = true;
                        }
                    }
                    if ($repeated) {
                        $numbers = Sample::whereIn('id', $repeated)->pluck('sample_number')->join(', ');
                        $validator->errors()->add("dispatches.$key.buyer_ids", "This sends {$numbers} to a buyer that an earlier dispatch on this form already sends it to.");
                    }

                    $tooEarly = Sample::whereIn('id', $group['sample_ids'])->whereDate('collection_date', '>', $group['dispatch_date'])->pluck('sample_number');
                    if ($tooEarly->isNotEmpty()) {
                        $validator->errors()->add("dispatches.$key.dispatch_date", 'The dispatch date is before the collection date of '.$tooEarly->join(', ').'.');
                    }
                }
            },
        ];
    }

    /**
     * The dispatches in the order they were entered, keyed as on the form.
     *
     * @return array<string|int, array{sample_ids: list<int>, buyer_ids: list<int>, dispatch_date: string, notes: ?string}>
     */
    public function groups(): array
    {
        return collect($this->input('dispatches', []))->map(fn (array $group) => [
            'sample_ids' => array_values(array_unique(array_map('intval', (array) ($group['sample_ids'] ?? [])))),
            'buyer_ids' => array_values(array_unique(array_map('intval', (array) ($group['buyer_ids'] ?? [])))),
            'dispatch_date' => (string) ($group['dispatch_date'] ?? ''),
            'notes' => filled($group['notes'] ?? null) ? trim($group['notes']) : null,
        ])->all();
    }
}
