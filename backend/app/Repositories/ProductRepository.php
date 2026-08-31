<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductRepository
{

    public function modelQuery(): Builder| Product{
        return Product::query();
    }

    public function getProductById(int $id): Product{
        return $this->modelQuery()
            ->with([
                'productAttributeValues.attribute',
                'productAttributeValues.attributeOption',
                'productAttributeValues.media',
            ])
            ->where('id', '=', $id)->firstOrFail();
    }

    public function getProductBySlug($slug): Product{
        $product = $this->modelQuery()
            ->with([
                'productAttributeValues.attribute',
                'productAttributeValues.attributeOption',
            ])
            ->where('slug', '=', $slug)->firstOrFail();

        $product->productAttributeValues
            ->filter(fn ($pav) => $pav->attribute->name === 'attribute.color')
            ->load('media');

        return $product;
    }

    /**
     * @param int[] $productIds
     */
    public function findProductsByIdsWithDefaultAttributes(array $productIds): Collection{
        return $this->modelQuery()
            ->with([
                'attributes.attributeOptions' => function ($query) {
                    $query->orderBy('id')->limit(1);
                }
            ])
            ->whereIn('id', $productIds)
            ->get();
    }

    /**
     * @param int[] $productIds
     */
    public function findProductsByIds(array $productIds): Collection{
        return $this->modelQuery()
            ->with([
                'attributes.attributeOptions',
                'productAttributeValues.attribute',
                'productAttributeValues.attributeOption',
            ])
            ->whereIn('id', $productIds)
            ->distinct()
            ->get();
    }

    /**
     * @param  int[]  $productIds
     * @return array<int, int>
     */
    public function getDistinctSupplierIds(array $productIds): array{
        return $this->modelQuery()
            ->whereIn('id', $productIds)
            ->distinct()
            ->pluck('supplier_id')
            ->all();
    }

    public function findProductsForCart(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        return $this->modelQuery()
            ->with([
                'productAttributeValues.attribute',
                'productAttributeValues.media',
            ])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
    }

    public function updateRatingStats(int $productId, ?float $averageRating, int $reviewsCount): bool
    {
        return $this->modelQuery()->where('id', $productId)->update([
            'average_rating' => $averageRating,
            'reviews_count' => $reviewsCount,
        ]);
    }

    public function lockForUpdate(int $productId): Product
    {
        return $this->modelQuery()->where('id', $productId)->lockForUpdate()->firstOrFail();
    }
}
