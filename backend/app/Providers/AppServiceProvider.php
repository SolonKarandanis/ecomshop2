<?php

namespace App\Providers;

use App\Enums\UserStatusEnum;
use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Observers\OrderStatusHistoryObserver;
use App\Search\ProductSearchEngineFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
        $this->registerQueryLogging();

        Schema::defaultStringLength(191);
    }

    private function registerObservers(): void
    {
        Order::observe([OrderObserver::class, OrderStatusHistoryObserver::class]);
    }

    private function registerGates(): void
    {
        Gate::define('buyer-action', function (?User $user) {
            return $user === null || $user->isBuyer();
        });

        Gate::define('supplier-action', function (?User $user) {
            return config('features.suppliers_enabled')
                && ($user === null || ($user->isSupplier() && $user->status === UserStatusEnum::ACTIVE));
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

    // Opt-in with LOG_QUERIES=true. Logs each query with its bindings filled in
    // and how long it took; never in production, where it would flood the log.
    private function registerQueryLogging(): void
    {
        if (! config('app.log_queries') || app()->isProduction()) {
            return;
        }

        // An idle queue worker reads the cache every few seconds for restart and
        // pause signals, so in a worker only the queries run by a job are logged.
        $logging = true;
        if ($this->app->runningConsoleCommand('queue:work')) {
            $logging = false;
            Event::listen(JobProcessing::class, function () use (&$logging) {
                $logging = true;
            });
            Event::listen([JobProcessed::class, JobFailed::class], function () use (&$logging) {
                $logging = false;
            });
        }

        DB::listen(function (QueryExecuted $query) use (&$logging) {
            if ($logging) {
                Log::debug(sprintf('[%s, %.2f ms] %s', $query->connectionName, $query->time, $query->toRawSql()));
            }
        });
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
