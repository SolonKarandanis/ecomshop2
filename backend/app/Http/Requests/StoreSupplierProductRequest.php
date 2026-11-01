<?php

namespace App\Http\Requests;

use App\Models\AttributeOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// `supplier_id` and `is_featured` have no rule, so validated() never carries them:
// the owner always comes from the session, and featuring stays admin-only.
class StoreSupplierProductRequest extends FormRequest
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
            // `name` and `description` are the request's language (#81); a block
            // per language, e.g. {"el": {"name": …}}, sets that language. At
            // least one name is required.
            'name' => ['required_without_all:'.$this->localeNameFields(), 'nullable', 'string', 'max:255'],
            ...$this->localeRules(),
            // Generated from the name when omitted (ProductService::createSupplierProduct).
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'is_active' => ['sometimes', 'boolean'],
            'in_stock' => ['sometimes', 'boolean'],
            'on_sale' => ['sometimes', 'boolean'],
            'attributes' => ['sometimes', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.attribute_option_id' => ['required', 'integer', 'exists:attribute_options,id'],
        ];
    }

    /**
     * Each language's block: optional, but a block that's sent needs a name.
     *
     * @return array<string, mixed>
     */
    protected function localeRules(): array
    {
        $rules = [];
        foreach (config('translatable.locales') as $locale) {
            $rules[$locale] = ['sometimes', 'array:name,description'];
            $rules["{$locale}.name"] = ["required_with:{$locale}", 'string', 'max:255'];
            $rules["{$locale}.description"] = ['nullable', 'string'];
        }

        return $rules;
    }

    protected function localeNameFields(): string
    {
        return 'translatable.locales'
                |> config(...)
                |> (fn($x) => array_map(fn(string $locale) => "{$locale}.name", $x))
                |> (fn($x) => implode(',', $x));
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['attributes', 'attributes.*'])) {
                    return;
                }

                $pairs = $this->input('attributes', []);
                $optionAttributes = AttributeOptions::whereIn('id', array_column($pairs, 'attribute_option_id'))
                    ->pluck('attribute_id', 'id');

                $seen = [];
                foreach ($pairs as $index => $pair) {
                    $key = "attributes.{$index}.attribute_option_id";
                    $optionId = (int) $pair['attribute_option_id'];

                    if ((int) $optionAttributes[$optionId] !== (int) $pair['attribute_id']) {
                        $validator->errors()->add($key, __('messages.supplier_products.option_not_of_attribute'));
                    } elseif (isset($seen[$optionId])) {
                        $validator->errors()->add($key, __('messages.supplier_products.duplicate_attribute_option'));
                    } else {
                        $seen[$optionId] = true;
                    }
                }
            },
        ];
    }
}
