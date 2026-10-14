<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Search\ProductSearchEngineFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        DB::prohibitDestructiveCommands(
            app()->isProduction()
        );

        // Not attribute-bindable: needs computed constructor args (DB driver name,
        // a config value), not just class dependencies. Every other Service/Payment
        // handler singleton is declared via #[Singleton] on the class itself instead.
        $this->app->singleton(ProductSearchEngineFactory::class, fn ($app) => new ProductSearchEngineFactory(
            DB::connection()->getDriverName(),
            (bool) config('search.fts_enabled'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerObservers();
        $this->registerGates();
        $this->registerPasswordResetUrl();

        Schema::defaultStringLength(191);
    }

    private function registerObservers(): void
    {
        Order::observe(OrderObserver::class);
    }

    private function registerGates(): void
    {
        Gate::define('buyer-action', function (?User $user) {
            return $user === null || $user->isBuyer();
        });

        Gate::define('supplier-action', function (?User $user) {
            return config('features.suppliers_enabled') && ($user === null || $user->isSupplier());
        });

        Gate::define('admin-action', function (?User $user) {
            return $user === null || $user->isAdmin();
        });
    }

    // There is no `password.reset` web route in this API-only app; the reset
    // form is the Nuxt `/reset-password` page, which posts to `/api/reset-password`.
    private function registerPasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
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
