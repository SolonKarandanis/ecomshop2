<?php

namespace App\Search;

use App\Dtos\ProductSearchFilterDto;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseProductSearchEngine implements ProductSearchEngineInterface
{
    private array $sortBy = ['latest' => 'created_at', 'price' => 'price', 'rating' => 'average_rating'];
    public function search(ProductSearchFilterDto $dto): LengthAwarePaginator
    {
        $productQuery = Product::query()
            ->with([
                'productAttributeValues' => function ($query) {
                    $query->whereHas('attribute', function ($query) {
                        $query->where('name', 'attribute.color');
                    })->with(['media', 'attribute']);
                }
            ])
            ->where('is_active', true)
            ->whereBetween('price', [$dto->getPriceFrom(), $dto->getPriceTo()]);

        $productQuery->when(!empty($dto->getSelectedCategories()), function ($query) use ($dto) {
            $query->whereIn('category_id', $dto->getSelectedCategories());
        });

        $productQuery->when(!empty($dto->getSelectedBrands()), function ($query) use ($dto) {
            $query->whereIn('brand_id', $dto->getSelectedBrands());
        });

        $productQuery->when($dto->isFeatured(), function ($query) use ($dto) {
            $query->where('is_featured', true);
        });

        $productQuery->when($dto->isOnSale(), function ($query) use ($dto) {
            $query->where('on_sale', true);
        });

        $term = trim($dto->getSearch());
        $productQuery->when(mb_strlen($term) >= 3, function ($query) use ($term) {
            $this->applySearchTerm($query, $term);
        });

        $sortColumn = $this->sortBy[$dto->getSort()] ?? $this->sortBy['latest'];
        $productQuery->orderBy($sortColumn, 'desc');

        return $productQuery->paginate(6);
    }

    abstract protected function applySearchTerm(Builder $query, string $term): void;
}
