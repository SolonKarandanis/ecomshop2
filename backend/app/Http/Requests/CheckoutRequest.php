<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
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
            // Only methods an admin has left on, so a client can't pick a disabled one (e.g. PayPal).
            'paymentMethod' => ['required', 'string', Rule::exists('payment_methods', 'resource_key')->where('is_active', true)],
        ];
    }
}
