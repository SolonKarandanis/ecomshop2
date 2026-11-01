<?php

namespace App\Search;

use App\Dtos\ProductSearchFilterDto;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;

class MeilisearchProductSearchEngine implements ProductSearchEngineInterface
{
    public function search(ProductSearchFilterDto $dto): LengthAwarePaginator
    {
        $term = trim($dto->getSearch());
        $searchTerm = mb_strlen($term) >= 3 ? $term : '';

        // Only the active language's fields, which already hold the fallback
        // text for an untranslated Product (Product::toSearchableArray, #81).
        $locale = App::getLocale();
        $builder = Product::search($searchTerm)
            ->options(['attributesToSearchOn' => ["name_{$locale}", "description_{$locale}"]]);

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
