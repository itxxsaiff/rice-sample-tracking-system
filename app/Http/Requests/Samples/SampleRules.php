<?php

namespace App\Http\Requests\Samples;

use Illuminate\Validation\Rule;

/** Validation rules shared by the add-samples, edit-sample and offline sync paths. */
final class SampleRules
{
    /** Photos are no longer taken for samples; kept only for uploads queued on phones before that change. */
    public static function photo(): array
    {
        return ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.config('rice.max_photo_kb')];
    }

    /**
     * @param  int|null  $currentVarietyId  keeps an existing sample valid if its variety was later deactivated
     */
    public static function fields(string $prefix = '', ?int $currentVarietyId = null): array
    {
        return [
            $prefix.'mill_id' => ['required', 'integer', 'exists:mills,id'],
            $prefix.'rice_variety_id' => ['required', 'integer', Rule::exists('rice_varieties', 'id')->where(
                fn ($q) => $q->where('is_active', true)->when($currentVarietyId, fn ($q) => $q->orWhere('id', $currentVarietyId))
            )],
            $prefix.'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            $prefix.'quantity_unit' => ['nullable', 'required_with:'.$prefix.'quantity', Rule::in(array_keys(config('rice.sample_units')))],
            $prefix.'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
