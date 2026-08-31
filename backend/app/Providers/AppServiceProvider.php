<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Payments\PaymentHandlerFactory;
use App\Payments\StripePaymentHandler;
use App\Repositories\AddressRepository;
use App\Repositories\CartRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StripeOrderDetailRepository;
use App\Repositories\UserRepository;
use App\Search\ProductSearchEngineFactory;
use App\Services\CartService;
use App\Services\NotificationHandlerService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\ProductService;
use App\Services\ReviewService;
use App\Services\StripeService;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private function registerServices(): void
    {
        $this->app->singleton(ProductService::class, function ($app) {
            return new ProductService(
                $app->make(ProductRepository::class),
                $app->make(ProductSearchEngineFactory::class),
            );
        });

        $this->app->singleton(CartService::class, function ($app) {
            return new CartService($app->make(CartRepository::class), $app->make(ProductService::class));
        });

        $this->app->singleton(UserService::class, function ($app) {
            return new UserService(
                $app->make(UserRepository::class),
                $app->make(RoleRepository::class),
            );
        });

        $this->app->singleton(StripeService::class, function ($app) {
            return new StripeService;
        });

        $this->app->singleton(StripePaymentHandler::class, function ($app) {
            return new StripePaymentHandler($app->make(StripeService::class), $app->make(StripeOrderDetailRepository::class));
        });

        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService(
                $app->make(OrderRepository::class),
                $app->make(AddressRepository::class),
                $app->make(PaymentMethodRepository::class),
                $app->make(CartService::class),
                $app->make(StripeService::class),
                $app->make(PaymentHandlerFactory::class),
                $app->make(NotificationHandlerService::class),
                $app->make(ProductRepository::class),
            );
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService($app->make(NotificationRepository::class));
        });

        $this->app->singleton(ReviewService::class, function ($app) {
            return new ReviewService(
                $app->make(ReviewRepository::class),
                $app->make(OrderRepository::class),
                $app->make(ProductService::class),
            );
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        DB::prohibitDestructiveCommands(
            app()->isProduction()
        );

        $this->app->singleton(ProductSearchEngineFactory::class, fn ($app) => new ProductSearchEngineFactory(
            DB::connection()->getDriverName(),
            (bool) config('search.fts_enabled'),
        ));

        $this->registerServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerObservers();

        Schema::defaultStringLength(191);
    }

    private function registerObservers(): void
    {
        Order::observe(OrderObserver::class);
    }

    private function registerConfig(): void
    {
        if (! config('search.fts_enabled')) {
            config(['scout.driver' => null]);
        }

        if (config('database.default') === 'sqlite' &&
            file_exists(config('database.connections.sqlite.database'))) {
            DB::statement('PRAGMA journal_mode=WAL');
            DB::statement('PRAGMA synchronous=NORMAL');
            DB::statement('PRAGMA cache_size=10000');
            DB::statement('PRAGMA temp_store=MEMORY');
        }
    }
}
