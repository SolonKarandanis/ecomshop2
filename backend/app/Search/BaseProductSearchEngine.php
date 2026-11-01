<?php

namespace App\Search;

use App\Dtos\ProductSearchFilterDto;
use App\Models\Product;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;

abstract class BaseProductSearchEngine implements ProductSearchEngineInterface
{
    public function search(ProductSearchFilterDto $dto): LengthAwarePaginator
    {
        $productQuery = Product::query()
            ->with([
                'category',
                'brand',
                'productAttributeValues' => function ($query) {
                    $query->whereHas('attribute', function ($query) {
                        $query->where('name', 'attribute.color');
                    })->with(['media', 'attribute']);
                },
            ])
            ->where('is_active', true);

        $productQuery->when($dto->getPriceFrom() !== null, function ($query) use ($dto) {
            $query->where('price', '>=', $dto->getPriceFrom());
        });

        $productQuery->when($dto->getPriceTo() !== null, function ($query) use ($dto) {
            $query->where('price', '<=', $dto->getPriceTo());
        });

        $productQuery->when(! empty($dto->getSelectedCategories()), function ($query) use ($dto) {
            $query->whereIn('category_id', $dto->getSelectedCategories());
        });

        $productQuery->when(! empty($dto->getSelectedBrands()), function ($query) use ($dto) {
            $query->whereIn('brand_id', $dto->getSelectedBrands());
        });

        $productQuery->when($dto->isFeatured(), function ($query) {
            $query->where('is_featured', true);
        });

        $productQuery->when($dto->isOnSale(), function ($query) {
            $query->where('on_sale', true);
        });

        $term = trim($dto->getSearch());
        $productQuery->when(mb_strlen($term) >= 3, function ($query) use ($term) {
            $this->applySearchTerm($query, $term);
        });

        $productQuery->orderBy($dto->getSortColumn(), $dto->getSortDirection());

        return $productQuery->paginate($dto->getPerPage());
    }

    abstract protected function applySearchTerm(Builder $query, string $term): void;

    /**
     * Matches the text the storefront shows (#81): a Product's translation in
     * the active locale, or, for a Product with none, its other translations,
     * which the page falls back to. A Product that has the active locale isn't
     * matched on another language's text.
     *
     * @param  Closure(QueryBuilder): mixed  $matches  adds the match on `t.name` / `t.description`
     */
    protected function whereShownTextMatches(Builder $query, Closure $matches): void
    {
        $locale = App::getLocale();
        $translations = fn (QueryBuilder $subquery) => $subquery->from('product_translations as t')
            ->whereColumn('t.product_id', 'products.id');

        $query->where(fn (Builder $query) => $query
            ->whereExists(fn (QueryBuilder $subquery) => $matches($translations($subquery)->where('t.locale', $locale)))
            ->orWhere(fn (Builder $query) => $query
                ->whereNotExists(fn (QueryBuilder $subquery) => $translations($subquery)->where('t.locale', $locale))
                ->whereExists(fn (QueryBuilder $subquery) => $matches($translations($subquery)))));
    }
}
