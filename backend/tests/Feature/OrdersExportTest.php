<?php

use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Exports\OrdersExport;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

function ordersExportBuyer(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    return $buyer;
}

it("exports only the authenticated Buyer's own Orders as an xlsx spreadsheet", function () {
    Excel::fake();
    $buyer = ordersExportBuyer();
    $otherBuyer = ordersExportBuyer();
    Order::factory()->create(['user_id' => $buyer->id]);
    Order::factory()->count(2)->create(['user_id' => $otherBuyer->id]);

    $this->actingAs($buyer)->getJson('/orders/export')->assertOk();

    Excel::assertDownloaded('orders.xlsx', function (OrdersExport $export) use ($buyer) {
        $rows = $export->collection();

        return $rows->count() === 1 && $rows->first()->user_id === $buyer->id;
    });
});

it('respects the orderStatus filter on export', function () {
    Excel::fake();
    $buyer = ordersExportBuyer();
    Order::factory()->create(['user_id' => $buyer->id, 'order_status' => OrderStatusEnum::Paid->value]);
    Order::factory()->create(['user_id' => $buyer->id, 'order_status' => OrderStatusEnum::Draft->value]);

    $this->actingAs($buyer)
        ->getJson('/orders/export?orderStatus='.urlencode(OrderStatusEnum::Paid->value))
        ->assertOk();

    Excel::assertDownloaded('orders.xlsx', function (OrdersExport $export) {
        $rows = $export->collection();

        return $rows->count() === 1 && $rows->first()->order_status === OrderStatusEnum::Paid->value;
    });
});

it('rejects exporting Orders when the result would exceed 10,000 rows', function () {
    $buyer = ordersExportBuyer();
    $this->mock(OrderRepository::class, function ($mock) {
        $mock->shouldReceive('countOrders')->once()->andReturn(10001);
    });

    $this->actingAs($buyer)->getJson('/orders/export')->assertStatus(400);
});

it('rejects a guest from exporting Orders', function () {
    $this->getJson('/orders/export')->assertUnauthorized();
});

it('has no export endpoint for the Supplier Orders list', function () {
    $buyer = ordersExportBuyer();

    $this->actingAs($buyer)->getJson('/supplier-orders/export')->assertNotFound();
});
