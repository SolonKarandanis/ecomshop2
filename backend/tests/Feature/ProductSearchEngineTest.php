<?php

use App\Enums\ProductSearchEngineEnum;
use App\Search\ProductSearchEngineFactory;

it('picks the product search engine from the database driver and the FTS setting', function (string $driver, bool $ftsEnabled, ProductSearchEngineEnum $engine) {
    expect((new ProductSearchEngineFactory($driver, $ftsEnabled))->resolveEngine())->toBe($engine);
})->with([
    'MySQL' => ['mysql', false, ProductSearchEngineEnum::FullText],
    'MariaDB' => ['mariadb', false, ProductSearchEngineEnum::FullText],
    'SQLite' => ['sqlite', false, ProductSearchEngineEnum::Like],
    'PostgreSQL' => ['pgsql', false, ProductSearchEngineEnum::Like],
    'Meilisearch on MariaDB' => ['mariadb', true, ProductSearchEngineEnum::Meilisearch],
    'Meilisearch on SQLite' => ['sqlite', true, ProductSearchEngineEnum::Meilisearch],
]);
