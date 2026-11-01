<?php

use App\Enums\ProductSearchEngineEnum;
use App\Search\ProductSearchEngineFactory;
use Illuminate\Database\Connection;

it('picks the product search engine from the database driver and the FTS setting', function (string $driver, bool $ftsEnabled, ProductSearchEngineEnum $engine) {
    $connection = Mockery::mock(Connection::class);
    $connection->allows('getDriverName')->andReturn($driver);

    expect((new ProductSearchEngineFactory($connection, $ftsEnabled))->resolveEngine())->toBe($engine);
})->with([
    'MySQL' => ['mysql', false, ProductSearchEngineEnum::FullText],
    'MariaDB' => ['mariadb', false, ProductSearchEngineEnum::FullText],
    'SQLite' => ['sqlite', false, ProductSearchEngineEnum::Like],
    'PostgreSQL' => ['pgsql', false, ProductSearchEngineEnum::Like],
    'Meilisearch on MariaDB' => ['mariadb', true, ProductSearchEngineEnum::Meilisearch],
    'Meilisearch on SQLite' => ['sqlite', true, ProductSearchEngineEnum::Meilisearch],
]);

it('resolves from the container with the default connection and the FTS setting', function () {
    config(['search.fts_enabled' => false]);

    // The test database is SQLite, which has no FULLTEXT.
    expect(app(ProductSearchEngineFactory::class)->resolveEngine())->toBe(ProductSearchEngineEnum::Like);
});
