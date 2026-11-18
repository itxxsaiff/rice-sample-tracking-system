<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }
}
