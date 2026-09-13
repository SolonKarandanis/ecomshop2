<?php

namespace App\Data;

use App\Models\Product;
use Spatie\LaravelData\Lazy;

class ProductData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public float $price,
        public bool $is_featured,
        public bool $in_stock,
        public bool $on_sale,
        public ?float $average_rating,
        public int $reviews_count,
        public string $thumbnail,
        public string $image,
        public Lazy|CategoryData|null $category,
        public Lazy|BrandData|null $brand,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            slug: $product->slug,
            description: $product->description,
            price: (float) $product->price,
            is_featured: (bool) $product->is_featured,
            in_stock: (bool) $product->in_stock,
            on_sale: (bool) $product->on_sale,
            average_rating: $product->average_rating !== null ? (float) $product->average_rating : null,
            reviews_count: $product->reviews_count,
            thumbnail: $product->getThumbnailImage(),
            image: $product->getLargeImage(),
            category: Lazy::whenLoaded('category', $product, fn () => CategoryData::fromModel($product->category)),
            brand: Lazy::whenLoaded('brand', $product, fn () => BrandData::fromModel($product->brand)),
        );
    }
}
