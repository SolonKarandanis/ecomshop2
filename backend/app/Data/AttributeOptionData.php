<?php

namespace App\Data;

use App\Models\AttributeOptions;

class AttributeOptionData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $label,
    ) {}

    public static function fromModel(AttributeOptions $option): self
    {
        return new self(
            id: $option->id,
            name: $option->option_name,
            label: __("product-attributes.{$option->option_name}"),
        );
    }
}
