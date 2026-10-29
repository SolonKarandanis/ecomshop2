<?php

namespace App\Http\Requests;

// A partial update: only the fields sent are validated and changed.
class UpdateSavedAddressRequest extends StoreSavedAddressRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_map(fn (array $rules) => ['sometimes', ...$rules], parent::rules());
    }
}
