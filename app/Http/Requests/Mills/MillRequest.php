<?php

namespace App\Http\Requests\Mills;

use App\Models\Mill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MillRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mill = $this->route('mill');

        return $mill ? $this->user()->can('update', $mill) : $this->user()->can('create', Mill::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->squish('name'),
            'city' => $this->squish('city'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('mills')->where('city', $this->input('city'))->ignore($this->route('mill')),
            ],
            'city' => ['required', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'A mill with this name already exists in this city. Open the existing profile instead of creating a duplicate.'];
    }

    private function squish(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) ? preg_replace('/\s+/', ' ', trim($value)) : $value;
    }
}
