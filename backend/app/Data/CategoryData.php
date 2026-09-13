<?php

namespace App\Data;

use App\Models\Category;

class CategoryData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $thumbnail,
    ) {}

    public static function fromModel(Category $category): self
    {
        return new self(
            id: $category->id,
            name: $category->name,
            slug: $category->slug,
            thumbnail: $category->getThumbnailImage(),
        );
    }
}
