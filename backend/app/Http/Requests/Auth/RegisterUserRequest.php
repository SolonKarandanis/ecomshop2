<?php

namespace App\Http\Requests\Auth;

use App\Enums\RolesEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'email' => ['required', 'email', 'max:200', 'unique:users'],
            'password' => ['required', 'string', Rules\Password::defaults()],
            'role' => ['sometimes', 'string', Rule::in($this->allowedRoles())],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedRoles(): array
    {
        return config('features.suppliers_enabled')
            ? [RolesEnum::ROLE_BUYER->value, RolesEnum::ROLE_SUPPLIER->value]
            : [RolesEnum::ROLE_BUYER->value];
    }
}
