<?php

namespace App\Search;

use App\Dtos\ProductSearchFilterDto;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class MeilisearchProductSearchEngine implements ProductSearchEngineInterface
{
    private array $sortBy = ['latest' => 'created_at', 'price' => 'price', 'rating' => 'average_rating'];

    public function search(ProductSearchFilterDto $dto): LengthAwarePaginator
    {
        $term = trim($dto->getSearch());
        $searchTerm = mb_strlen($term) >= 3 ? $term : '';

        $builder = Product::search($searchTerm);

        $builder->when(!empty($dto->getSelectedCategories()), function ($query) use ($dto) {
            $query->whereIn('category_id', $dto->getSelectedCategories());
        });

        $builder->when(!empty($dto->getSelectedBrands()), function ($query) use ($dto) {
            $query->whereIn('brand_id', $dto->getSelectedBrands());
        });

        $builder->where('price', '>=', $dto->getPriceFrom());
        $builder->where('price', '<=', $dto->getPriceTo());

        $builder->when($dto->isFeatured(), function ($query) {
            $query->where('is_featured', true);
        });

        $builder->when($dto->isOnSale(), function ($query) {
            $query->where('on_sale', true);
        });

        $sortColumn = $this->sortBy[$dto->getSort()] ?? $this->sortBy['latest'];
        $builder->orderBy($sortColumn, 'desc');

        return $builder->paginate(6);
    }
}
