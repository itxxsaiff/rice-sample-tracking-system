<?php

namespace App\Http\Requests\Samples;

use Illuminate\Validation\Rule;

/** Validation rules shared by single-sample and bundle forms. */
final class SampleRules
{
    public static function photo(): array
    {
        return ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.config('rice.max_photo_kb')];
    }

    public static function sampleNumber(?int $ignoreId = null): array
    {
        return ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\/_. ]*$/', Rule::unique('samples', 'sample_number')->ignore($ignoreId)];
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
            $prefix.'photo' => self::photo(),
        ];
    }

    public static function messages(): array
    {
        return [
            'sample_number.regex' => 'Use letters, numbers and dashes only.',
            'photo.mimes' => 'The photo must be a JPG, PNG, WEBP or HEIC image.',
            'photo.max' => 'The photo is too large (max :max KB).',
        ];
    }
}
