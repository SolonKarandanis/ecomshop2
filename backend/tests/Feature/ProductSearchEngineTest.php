<?php

use App\Enums\ProductSearchEngineEnum;
use App\Search\ProductSearchEngineFactory;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

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

it('resolves from the container with the default connection', function () {
    config(['search.fts_enabled' => false]);

    // Local tests run on SQLite and CI on MariaDB, so compare with the default connection's own answer.
    expect(app(ProductSearchEngineFactory::class)->resolveEngine())
        ->toBe((new ProductSearchEngineFactory(DB::connection(), false))->resolveEngine());
});

it('resolves from the container with the FTS setting from config', function () {
    config(['search.fts_enabled' => true]);

    expect(app(ProductSearchEngineFactory::class)->resolveEngine())->toBe(ProductSearchEngineEnum::Meilisearch);
});
