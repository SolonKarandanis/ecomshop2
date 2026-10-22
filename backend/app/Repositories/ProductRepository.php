<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductAttributeValues;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductRepository
{
    public function modelQuery(): Builder|Product
    {
        return Product::query();
    }

    public function getProductById(int $id): Product
    {
        return $this->modelQuery()
            ->with([
                'productAttributeValues.attribute',
                'productAttributeValues.attributeOption',
                'productAttributeValues.media',
            ])
            ->where('id', '=', $id)->firstOrFail();
    }

    public function getProductBySlug($slug): Product
    {
        $product = $this->modelQuery()
            ->with([
                'category',
                'brand',
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
     * @param  int[]  $productIds
     */
    public function findProductsByIdsWithDefaultAttributes(array $productIds): Collection
    {
        return $this->modelQuery()
            ->with([
                'attributes.attributeOptions' => function ($query) {
                    $query->orderBy('id')->limit(1);
                },
            ])
            ->whereIn('id', $productIds)
            ->get();
    }

    /**
     * @param  int[]  $productIds
     */
    public function findProductsByIds(array $productIds): Collection
    {
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
    public function getDistinctSupplierIds(array $productIds): array
    {
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

    public function getProductsBySupplier(int $supplierId, int $perPage): LengthAwarePaginator
    {
        return $this->modelQuery()
            ->with(['category.media', 'brand', 'media'])
            ->where('supplier_id', $supplierId)
            ->latest('id')
            ->paginate($perPage);
    }

    public function getProductForManagement(int $id): Product
    {
        return $this->modelQuery()
            ->with(['category.media', 'brand', 'productAttributeValues', 'media'])
            ->where('id', '=', $id)->firstOrFail();
    }

    public function create(array $fields): Product
    {
        return $this->modelQuery()->create($fields);
    }

    public function update(Product $product, array $fields): Product
    {
        $product->update($fields);

        return $product;
    }

    public function slugExists(string $slug): bool
    {
        return $this->modelQuery()->where('slug', $slug)->exists();
    }

    public function hasOrderItems(int $productId): bool
    {
        return $this->modelQuery()->whereKey($productId)->whereHas('orderItems')->exists();
    }

    /**
     * Makes the Product's attribute values exactly $pairs. Rows whose pair is kept
     * stay as they are, so their attribute images (uploaded in Filament) survive.
     *
     * @param  array<int, array{attribute_id: int, attribute_option_id: int}>  $pairs
     */
    public function syncAttributeValues(Product $product, array $pairs): void
    {
        $wanted = collect($pairs)->keyBy(fn (array $pair) => $pair['attribute_id'].':'.$pair['attribute_option_id']);

        $product->productAttributeValues()->get()->each(function (ProductAttributeValues $value) use ($wanted) {
            $key = $value->attribute_id.':'.$value->attribute_option_id;
            if ($wanted->has($key)) {
                $wanted->forget($key);
            } else {
                // Through the model, not a query delete, so its media is removed too.
                $value->delete();
            }
        });

        $wanted->each(fn (array $pair) => $product->productAttributeValues()->create($pair));
    }

    public function delete(Product $product): void
    {
        // The attribute values would cascade in the database, but deleting them
        // through their models also removes their attribute images.
        $product->productAttributeValues()->get()->each->delete();
        $product->delete();
    }
}
