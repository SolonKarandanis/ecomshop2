<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

// A partial update: only the fields sent are validated and changed. Sending
// `attributes` replaces the Product's attribute values; omitting it keeps them.
class UpdateSupplierProductRequest extends StoreSupplierProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('products', 'slug')->ignore((int) $this->route('product'))],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'required', 'integer', 'exists:brands,id'],
        ];
    }
}
