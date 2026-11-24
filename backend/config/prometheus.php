<?php

use App\Http\Middleware\AuthenticatePrometheusScrape;
use Spatie\Prometheus\Actions\RenderCollectorsAction;

return [
    'enabled' => env('PROMETHEUS_ENABLED', true),

    /*
     * The bearer token a scraper must send. When empty, every request to the
     * urls below is refused with a 401.
     */
    'token' => env('PROMETHEUS_TOKEN'),

    /*
     * The urls that will return metrics.
     */
    'urls' => [
        'default' => 'prometheus',
    ],

    /*
     * This is the default namespace that will be
     * used by all metrics
     */
    'default_namespace' => 'ecomshop',

    /*
     * The middleware that will be applied to the urls above
     */
    'middleware' => [
        AuthenticatePrometheusScrape::class,
    ],

    /*
     * You can override these classes to customize low-level behaviour of the package.
     * In most cases, you can just use the defaults.
     */
    'actions' => [
        'render_collectors' => RenderCollectorsAction::class,
    ],

    /**
     * Allow storage to be wiped after a render of data in metrics controller.
     */
    'wipe_storage_after_rendering' => false,

    /**
     * Select a cache to store gauges, counters, summaries and histograms between requests.
     * In a multi node setup you should ensure that each node writes to its own
     * cache instance or uses a node specific prefix.
     * Configure the cache store in config/cache.php.
     *
     * to use an in memory adapter for testing use array or null as your store
     * or remove the cache entry all together:
     *  'cache' => null       // InMemory implementation without laravel cache
     *  'cache' => 'array'    // InMemory implementation using laravel cache
     */
    'cache' => null,

    /*
     * Lock each metric type while updating cached metrics to prevent concurrent
     * writers from overwriting each other. Requires a cache store with lock support.
     * Throws if the lock cannot be acquired within 3 seconds or is unsupported.
     */
    'cache_lock' => false,
];
