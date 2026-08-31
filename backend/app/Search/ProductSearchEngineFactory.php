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
        return $this->driver === 'mysql'
            ? ProductSearchEngineEnum::FullText
            : ProductSearchEngineEnum::Like;
    }
}
