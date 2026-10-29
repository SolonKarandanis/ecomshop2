<?php

namespace App\Search;

use App\Enums\ProductSearchEngineEnum;

class ProductSearchEngineFactory
{
    public function __construct(
        private readonly string $driver,
        private readonly bool $ftsEnabled,
    ){}
    public function make():ProductSearchEngineInterface{
        return match ($this->resolveEngine()){
            ProductSearchEngineEnum::Like=>app(LikeProductSearchEngine::class),
            ProductSearchEngineEnum::FullText => app(FullTextProductSearchEngine::class),
            ProductSearchEngineEnum::Meilisearch => app(MeilisearchProductSearchEngine::class),
        };
    }

    public function resolveEngine():ProductSearchEngineEnum{
        if ($this->ftsEnabled) {
            return ProductSearchEngineEnum::Meilisearch;
        }
        // MariaDB has its own driver name since Laravel 11, but the same FULLTEXT support.
        return in_array($this->driver, ['mysql', 'mariadb'], true)
            ? ProductSearchEngineEnum::FullText
            : ProductSearchEngineEnum::Like;
    }
}
