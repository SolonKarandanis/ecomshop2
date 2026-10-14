<?php

namespace App\Search;

use App\Dtos\ProductSearchFilterDto;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class MeilisearchProductSearchEngine implements ProductSearchEngineInterface
{
    public function search(ProductSearchFilterDto $dto): LengthAwarePaginator
    {
        $term = trim($dto->getSearch());
        $searchTerm = mb_strlen($term) >= 3 ? $term : '';

        $builder = Product::search($searchTerm);

        $builder->when(! empty($dto->getSelectedCategories()), function ($query) use ($dto) {
            $query->whereIn('category_id', $dto->getSelectedCategories());
        });

        $builder->when(! empty($dto->getSelectedBrands()), function ($query) use ($dto) {
            $query->whereIn('brand_id', $dto->getSelectedBrands());
        });

        $builder->when($dto->getPriceFrom() !== null, function ($query) use ($dto) {
            $query->where('price', '>=', $dto->getPriceFrom());
        });

        $builder->when($dto->getPriceTo() !== null, function ($query) use ($dto) {
            $query->where('price', '<=', $dto->getPriceTo());
        });

        $builder->when($dto->isFeatured(), function ($query) {
            $query->where('is_featured', true);
        });

        $builder->when($dto->isOnSale(), function ($query) {
            $query->where('on_sale', true);
        });

        $builder->orderBy($dto->getSortColumn(), $dto->getSortDirection());

        return $builder->paginate($dto->getPerPage());
    }
}
