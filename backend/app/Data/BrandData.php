<?php

namespace App\Data;

use App\Models\Brand;

class BrandData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}

    public static function fromModel(Brand $brand): self
    {
        return new self(
            id: $brand->id,
            name: $brand->name,
            slug: $brand->slug,
        );
    }
}
