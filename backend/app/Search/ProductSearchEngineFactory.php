<?php

namespace App\Search;

use App\Enums\ProductSearchEngineEnum;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\DB;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Connection;

#[Singleton]
class ProductSearchEngineFactory
{
    private readonly string $driver;

    public function __construct(
        #[DB] Connection $connection,
        #[Config('search.fts_enabled')] private readonly bool $ftsEnabled,
    ) {
        $this->driver = $connection->getDriverName();
    }

    public function make(): ProductSearchEngineInterface
    {
        return match ($this->resolveEngine()) {
            ProductSearchEngineEnum::Like => app(LikeProductSearchEngine::class),
            ProductSearchEngineEnum::FullText => app(FullTextProductSearchEngine::class),
            ProductSearchEngineEnum::Meilisearch => app(MeilisearchProductSearchEngine::class),
        };
    }

    public function resolveEngine(): ProductSearchEngineEnum
    {
        if ($this->ftsEnabled) {
            return ProductSearchEngineEnum::Meilisearch;
        }

        // MariaDB has its own driver name since Laravel 11, but the same FULLTEXT support.
        return in_array($this->driver, ['mysql', 'mariadb'], true)
            ? ProductSearchEngineEnum::FullText
            : ProductSearchEngineEnum::Like;
    }
}
