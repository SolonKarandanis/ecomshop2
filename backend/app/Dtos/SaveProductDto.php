<?php

namespace App\Dtos;

use App\Http\Requests\StoreSupplierProductRequest;
use Illuminate\Support\Arr;

class SaveProductDto
{
    private const BOOLEAN_FIELDS = ['is_active', 'in_stock', 'on_sale'];

    /** @var array<string, mixed> Only the Product columns that were sent. */
    private array $fields;

    /** @var array<int, array{attribute_id: int, attribute_option_id: int}>|null null when not sent. */
    private ?array $attributeValues;

    public static function fromRequest(StoreSupplierProductRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();

        $fields = Arr::except($data, ['attributes']);
        foreach (self::BOOLEAN_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                $fields[$field] = (bool) $fields[$field];
            }
        }
        $instance->fields = $fields;

        $instance->attributeValues = array_key_exists('attributes', $data)
            ? array_map(fn (array $pair) => [
                'attribute_id' => (int) $pair['attribute_id'],
                'attribute_option_id' => (int) $pair['attribute_option_id'],
            ], array_values($data['attributes']))
            : null;

        return $instance;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getAttributeValues(): ?array
    {
        return $this->attributeValues;
    }
}
