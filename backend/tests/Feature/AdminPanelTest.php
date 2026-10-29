<?php

use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function makeAdmin(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value]);
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    return $admin;
}

it('serves the admin login page', function () {
    $this->get('/admin/login')->assertOk();
});

it('denies panel access to a guest', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets a seeded admin user log into the panel', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('denies panel access to an inactive admin-role user', function () {
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create(['status' => UserStatusEnum::INACTIVE->value]);
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    $this->actingAs($admin)->get('/admin')->assertForbidden();
});

it('denies panel access to a non-admin user', function () {
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    $this->actingAs($buyer)->get('/admin')->assertForbidden();
});

it('serves the Order, Product, and User resource index pages to an admin', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->get('/admin/orders')->assertOk();
    $this->actingAs($admin)->get('/admin/products')->assertOk();
    $this->actingAs($admin)->get('/admin/users')->assertOk();
});

it('locks order_status once an Order reaches a terminal status (ADR-0009)', function () {
    $admin = makeAdmin();
    $order = Order::factory()->create(['order_status' => OrderStatusEnum::Delivered->value]);

    $this->actingAs($admin);

    Livewire::test(\App\Filament\Resources\Orders\Pages\EditOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['order_status' => OrderStatusEnum::Cancelled->value])
        ->call('save');

    expect($order->fresh()->order_status)->toBe(OrderStatusEnum::Delivered->value);
});

it('rejects an Order whose items span more than one Supplier (ADR-0011 / #23)', function () {
    $admin = makeAdmin();
    $supplierA = User::factory()->create();
    $supplierB = User::factory()->create();
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();
    $productA = Product::factory()->create(['supplier_id' => $supplierA->id, 'category_id' => $category->id, 'brand_id' => $brand->id]);
    $productB = Product::factory()->create(['supplier_id' => $supplierB->id, 'category_id' => $category->id, 'brand_id' => $brand->id]);
    $buyer = User::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'user_id' => $buyer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_status' => \App\Enums\OrderPaymentStatusEnum::PENDING->value,
            'order_status' => OrderStatusEnum::Draft->value,
            'currency' => 'eur',
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 1, 'unit_amount' => $productA->price->toDecimal(), 'total_amount' => $productA->price->toDecimal()],
                ['product_id' => $productB->id, 'quantity' => 1, 'unit_amount' => $productB->price->toDecimal(), 'total_amount' => $productB->price->toDecimal()],
            ],
        ])
        ->call('create');

    expect(Order::count())->toBe(0);
});

it('edits a Product price in Filament, and shows it formatted in the list', function () {
    $this->actingAs(makeAdmin());
    $product = Product::factory()->create(['price' => '10.00']);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertSchemaStateSet(['price' => '10.00'])
        ->fillForm(['price' => '12.34'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->price->toDecimal())->toBe('12.34');
    Livewire::test(ListProducts::class)->assertSee('$12.34');
});

it('shows an Order total formatted in the Orders table and the Order page', function () {
    $this->actingAs(makeAdmin());
    $order = Order::factory()->create(['grand_total' => '1034.43', 'shipping_amount' => '5.00']);

    Livewire::test(ListOrders::class)->assertSee('€1,034.43');
    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertSee('1,034.43');
});
