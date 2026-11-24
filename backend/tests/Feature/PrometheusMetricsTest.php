<?php

use App\Enums\UserStatusEnum;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Testing\TestResponse;

const PROMETHEUS_TEST_TOKEN = 'test-scrape-token';

beforeEach(function () {
    config(['prometheus.token' => PROMETHEUS_TEST_TOKEN]);
});

function scrapeMetrics(): TestResponse
{
    return test()->withToken(PROMETHEUS_TEST_TOKEN)->get('/prometheus');
}

function schedulerHeartbeatEvent(): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => $event->description === 'prometheus-scheduler-heartbeat');
}

it('returns 401 when no token is sent', function () {
    $this->get('/prometheus')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer');
});

it('returns 401 when the token is wrong', function () {
    $this->withToken('not-the-token')->get('/prometheus')->assertUnauthorized();
});

it('returns 401 to every request when no token is configured', function () {
    config(['prometheus.token' => null]);

    $this->withToken('')->get('/prometheus')->assertUnauthorized();
});

it('serves metrics in the Prometheus text format to a scraper with the token', function () {
    scrapeMetrics()
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8');
});

it('counts orders by order status and by payment status', function () {
    $customer = User::factory()->create();
    Order::factory()->recycle($customer)->count(2)->paid()->create();
    Order::factory()->recycle($customer)->cancelled()->create();
    Order::factory()->recycle($customer)->create();

    $response = scrapeMetrics();

    $response->assertOk()
        ->assertSee('ecomshop_orders{status="paid"} 2', false)
        ->assertSee('ecomshop_orders{status="cancelled"} 1', false)
        ->assertSee('ecomshop_orders{status="draft"} 1', false)
        ->assertSee('ecomshop_order_payments{status="paid"} 2', false)
        ->assertSee('ecomshop_order_payments{status="failed"} 1', false)
        ->assertSee('ecomshop_order_payments{status="pending"} 1', false);
});

it('labels orders that have no payment status yet as none', function () {
    Order::factory()->recycle(User::factory()->create())->create(['payment_status' => null]);

    scrapeMetrics()
        ->assertOk()
        ->assertSee('ecomshop_order_payments{status="none"} 1', false);
});

it('sums the grand totals of paid orders only, per currency', function () {
    $customer = User::factory()->create();
    Order::factory()->recycle($customer)->paid()->create(['grand_total' => 10.50, 'currency' => 'usd']);
    Order::factory()->recycle($customer)->shipped()->create(['grand_total' => 20, 'currency' => 'usd']);
    Order::factory()->recycle($customer)->paid()->create(['grand_total' => 5, 'currency' => 'eur']);
    Order::factory()->recycle($customer)->create(['grand_total' => 99, 'currency' => 'usd']);

    scrapeMetrics()
        ->assertOk()
        ->assertSee('ecomshop_paid_revenue{currency="USD"} 30.5', false)
        ->assertSee('ecomshop_paid_revenue{currency="EUR"} 5', false);
});

it('counts users by account status', function () {
    User::factory()->count(2)->create();
    User::factory()->create(['status' => UserStatusEnum::INACTIVE->value]);

    scrapeMetrics()
        ->assertOk()
        ->assertSee('ecomshop_users{status="active"} 2', false)
        ->assertSee('ecomshop_users{status="inactive"} 1', false);
});

it('counts active products and the active ones that are out of stock', function () {
    $supplier = User::factory()->create();
    Product::factory()->recycle($supplier)->create(['is_active' => true, 'in_stock' => true]);
    Product::factory()->recycle($supplier)->create(['is_active' => true, 'in_stock' => false]);
    Product::factory()->recycle($supplier)->create(['is_active' => false, 'in_stock' => false]);

    scrapeMetrics()
        ->assertOk()
        ->assertSee('ecomshop_active_products 2', false)
        ->assertSee('ecomshop_out_of_stock_products 1', false);
});

it('counts the jobs waiting in the failed jobs table', function () {
    app(FailedJobProviderInterface::class)->log('database', 'default', '{"uuid":"a"}', new RuntimeException('SMTP down'));
    app(FailedJobProviderInterface::class)->log('database', 'default', '{"uuid":"b"}', new RuntimeException('SMTP down'));

    scrapeMetrics()
        ->assertOk()
        ->assertSee('ecomshop_failed_jobs 2', false);
});

it('schedules the scheduler heartbeat every minute', function () {
    expect(schedulerHeartbeatEvent())->not->toBeNull()
        ->and(schedulerHeartbeatEvent()->expression)->toBe('* * * * *');
});

it('reports when the scheduler last ran', function () {
    $this->travelTo('2026-11-24 10:00:00 UTC');
    schedulerHeartbeatEvent()->run(app());

    scrapeMetrics()
        ->assertOk()
        ->assertSee('ecomshop_scheduler_last_run_timestamp_seconds 1795514400', false);
});

it('reports 0 as the scheduler\'s last run before it has run', function () {
    scrapeMetrics()
        ->assertOk()
        ->assertSee('ecomshop_scheduler_last_run_timestamp_seconds 0', false);
});
