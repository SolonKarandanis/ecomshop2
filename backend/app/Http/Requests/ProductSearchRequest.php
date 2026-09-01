<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'brands' => ['sometimes', 'array'],
            'brands.*' => ['integer', 'exists:brands,id'],
            'featured' => ['sometimes', 'boolean'],
            'on_sale' => ['sometimes', 'boolean'],
            'price_from' => ['sometimes', 'integer', 'min:0'],
            'price_to' => ['sometimes', 'integer', 'gte:price_from'],
            'sort' => ['sometimes', 'string', 'in:latest,price,rating'],
            'q' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
