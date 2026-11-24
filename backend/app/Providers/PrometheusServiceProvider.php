<?php

namespace App\Providers;

use App\Services\MetricsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Spatie\Prometheus\Collectors\Queue\QueueOldestPendingJobCollector;
use Spatie\Prometheus\Collectors\Queue\QueuePendingJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueSizeCollector;
use Spatie\Prometheus\Facades\Prometheus;

/**
 * Registers the gauges served at /prometheus. Each closure runs only when
 * Prometheus scrapes, so every value is read fresh from the database and
 * nothing is stored between requests (Hostinger offers no Redis or APCu).
 */
class PrometheusServiceProvider extends ServiceProvider
{
    /**
     * Cache key the scheduler heartbeat in routes/console.php writes every minute.
     */
    public const string SCHEDULER_HEARTBEAT_KEY = 'prometheus:scheduler-last-run';

    public function register(): void
    {
        $this->registerOrderGauges();
        $this->registerCatalogueGauges();
        $this->registerQueueGauges();
        $this->registerSchedulerGauge();
    }

    private function registerOrderGauges(): void
    {
        Prometheus::addGauge('Orders')
            ->helpText('Orders by order status')
            ->label('status')
            ->value(fn (): array => $this->metrics()->ordersByStatus());

        Prometheus::addGauge('Order payments')
            ->helpText('Orders by payment status')
            ->label('status')
            ->value(fn (): array => $this->metrics()->ordersByPaymentStatus());

        Prometheus::addGauge('Paid revenue')
            ->helpText('Sum of grand totals of paid orders, by currency')
            ->label('currency')
            ->value(fn (): array => $this->metrics()->paidRevenueByCurrency());
    }

    private function registerCatalogueGauges(): void
    {
        Prometheus::addGauge('Users')
            ->helpText('Users by account status')
            ->label('status')
            ->value(fn (): array => $this->metrics()->usersByStatus());

        Prometheus::addGauge('Active products')
            ->helpText('Products that are active in the catalogue')
            ->value(fn (): int => $this->metrics()->activeProducts());

        Prometheus::addGauge('Out of stock products')
            ->helpText('Active products that are out of stock')
            ->value(fn (): int => $this->metrics()->outOfStockProducts());
    }

    private function registerQueueGauges(): void
    {
        Prometheus::registerCollectorClasses([
            QueueSizeCollector::class,
            QueuePendingJobsCollector::class,
            QueueOldestPendingJobCollector::class,
        ], ['queues' => ['default']]);

        Prometheus::addGauge('Failed jobs')
            ->helpText('Jobs that failed every try and wait in the failed jobs table')
            ->value(fn (): int => $this->app->make('queue.failer')->count());
    }

    private function registerSchedulerGauge(): void
    {
        Prometheus::addGauge('Scheduler last run')
            ->name('scheduler_last_run_timestamp_seconds')
            ->helpText('Unix time the scheduler last ran; 0 if it has not run since the cache was cleared')
            ->value(fn (): int => (int) Cache::get(self::SCHEDULER_HEARTBEAT_KEY, 0));
    }

    /**
     * Resolved per scrape, not in register(), so no gauge touches the
     * database until Prometheus asks for it.
     */
    private function metrics(): MetricsService
    {
        return $this->app->make(MetricsService::class);
    }
}
