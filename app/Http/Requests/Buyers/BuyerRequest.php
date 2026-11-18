<?php

namespace App\Http\Requests\Buyers;

use App\Models\Buyer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $buyer = $this->route('buyer');

        return $buyer ? $this->user()->can('update', $buyer) : $this->user()->can('create', Buyer::class);
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
                Rule::unique('buyers')->where('city', $this->input('city'))->ignore($this->route('buyer')),
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
        return ['name.unique' => 'A buyer with this name already exists in this city. Open the existing profile instead of creating a duplicate.'];
    }

    private function squish(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) ? preg_replace('/\s+/', ' ', trim($value)) : $value;
    }
}
