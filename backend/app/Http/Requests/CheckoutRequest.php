<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'min:2', 'max:200'],
            'lastName' => ['required', 'string', 'min:2', 'max:200'],
            'phone' => ['required', 'string', 'min:2', 'max:200'],
            'address' => ['required', 'string', 'min:2', 'max:200'],
            'city' => ['required', 'string', 'min:2', 'max:200'],
            'country' => ['required', 'string', 'min:2', 'max:200'],
            'zipCode' => ['required', 'string', 'min:2', 'max:200'],
            'paymentMethod' => ['required', 'string'],
        ];
    }
}
