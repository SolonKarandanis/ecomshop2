<?php

namespace App\Data;

use App\Models\Attribute;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;

class AttributeData extends BaseData
{
    public function __construct(
        public int $id,
        // A translation key such as "attribute.color"; `label` is its translation.
        public string $name,
        public string $label,
        public string $type,
        public Lazy|DataCollection|null $options,
    ) {}

    public static function fromModel(Attribute $attribute): self
    {
        return new self(
            id: $attribute->id,
            name: $attribute->name,
            label: __("product-attributes.{$attribute->name}"),
            type: $attribute->type,
            options: Lazy::whenLoaded('attributeOptions', $attribute, fn () => AttributeOptionData::collect($attribute->attributeOptions, DataCollection::class)),
        );
    }
}
