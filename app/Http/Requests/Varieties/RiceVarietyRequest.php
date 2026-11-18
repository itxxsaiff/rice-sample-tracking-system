<?php

namespace App\Http\Requests\Varieties;

use App\Models\RiceVariety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiceVarietyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $variety = $this->route('variety');

        return $variety ? $this->user()->can('update', $variety) : $this->user()->can('create', RiceVariety::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim($this->input('name')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('rice_varieties')->ignore($this->route('variety'))],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'This variety already exists.'];
    }
}
