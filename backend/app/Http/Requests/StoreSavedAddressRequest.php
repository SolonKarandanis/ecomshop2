<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Same rules as the address part of CheckoutRequest, under AddressData's field names.
class StoreSavedAddressRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'min:2', 'max:200'],
            'last_name' => ['required', 'string', 'min:2', 'max:200'],
            'phone' => ['required', 'string', 'min:2', 'max:200'],
            'street_address' => ['required', 'string', 'min:2', 'max:200'],
            'city' => ['required', 'string', 'min:2', 'max:200'],
            'country' => ['required', 'string', 'min:2', 'max:200'],
            'postal_code' => ['required', 'string', 'min:2', 'max:200'],
        ];
    }
}
