<?php

namespace App\Http\Requests;

use App\Dtos\ProductSearchFilterDto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'sort' => ['sometimes', 'string', Rule::in(array_keys(ProductSearchFilterDto::SORTS))],
            'q' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
