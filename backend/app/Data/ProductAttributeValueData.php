<?php

namespace App\Data;

use App\Models\ProductAttributeValues;

class ProductAttributeValueData extends BaseData
{
    public function __construct(
        public int $id,
        public int $attribute_id,
        public int $attribute_option_id,
    ) {}

    public static function fromModel(ProductAttributeValues $value): self
    {
        return new self(
            id: $value->id,
            attribute_id: $value->attribute_id,
            attribute_option_id: $value->attribute_option_id,
        );
    }
}
