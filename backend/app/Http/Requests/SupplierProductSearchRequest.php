<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SupplierProductSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('supplier-action');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
