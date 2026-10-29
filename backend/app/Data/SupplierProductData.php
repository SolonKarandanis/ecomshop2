<?php

namespace App\Data;

use App\Models\Product;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;

// A Supplier's view of their own Product: unlike ProductData it includes
// inactive state, the raw ids the edit form needs, and the uploaded images.
class SupplierProductData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public float $price,
        public int $category_id,
        public int $brand_id,
        public bool $is_active,
        public bool $is_featured,
        public bool $in_stock,
        public bool $on_sale,
        public Lazy|CategoryData|null $category,
        public Lazy|BrandData|null $brand,
        public Lazy|DataCollection|null $attributes,
        public Lazy|DataCollection|null $images,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            slug: $product->slug,
            description: $product->description,
            price: $product->price->toFloat(),
            category_id: $product->category_id,
            brand_id: $product->brand_id,
            is_active: $product->is_active,
            is_featured: $product->is_featured,
            in_stock: $product->in_stock,
            on_sale: $product->on_sale,
            category: Lazy::whenLoaded('category', $product, fn () => CategoryData::fromModel($product->category)),
            brand: Lazy::whenLoaded('brand', $product, fn () => BrandData::fromModel($product->brand)),
            attributes: Lazy::whenLoaded('productAttributeValues', $product, fn () => ProductAttributeValueData::collect($product->productAttributeValues, DataCollection::class)),
            images: Lazy::whenLoaded('media', $product, fn () => ProductImageData::collect($product->getMedia(Product::IMAGES_COLLECTION), DataCollection::class)),
        );
    }
}
