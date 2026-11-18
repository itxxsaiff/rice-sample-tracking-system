<?php

namespace App\Http\Requests\Loadings;

use App\Http\Requests\Samples\SampleRules;
use Illuminate\Foundation\Http\FormRequest;

class LoadingPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addPhotos', $this->route('loading'));
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.config('rice.max_photos_per_upload')],
            'photos.*' => ['required', ...array_slice(SampleRules::photo(), 1)],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'Choose or take at least one photo.',
            'photos.max' => 'You can upload up to :max photos at a time.',
            'photos.*.mimes' => 'Photos must be JPG, PNG, WEBP or HEIC images.',
        ];
    }
}
