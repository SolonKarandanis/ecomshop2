<?php

use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Filament\Resources\Orders\Widgets\OrderBuyerStatsWidget;
use App\Filament\Resources\Orders\Widgets\OrderStatsWidget;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value]);
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);
    $this->actingAs($admin);
});

it('counts Orders in every status, including Delivered', function () {
    Order::factory()->count(2)->create();
    Order::factory()->paid()->create();
    Order::factory()->shipped()->create();
    Order::factory()->count(3)->delivered()->create();
    Order::factory()->cancelled()->create();

    Livewire::withoutLazyLoading()->test(OrderStatsWidget::class)
        ->assertSeeInOrder(['Draft', '2', 'Paid', '1', 'Shipped', '1', 'Delivered', '3', 'Cancelled', '1']);
});

it('averages only Orders that sold: paid and not cancelled', function () {
    Order::factory()->paid()->create(['grand_total' => 100]);
    Order::factory()->delivered()->create(['grand_total' => 300]);
    // Excluded: an unpaid Stripe draft, and a paid Order that was then cancelled.
    Order::factory()->create(['grand_total' => 5000]);
    Order::factory()->paid()->create(['grand_total' => 9000, 'order_status' => 'order.status.cancelled']);

    Livewire::withoutLazyLoading()->test(OrderBuyerStatsWidget::class)->assertSee('€200.00');
});

it('shows a zero average when nothing has sold yet', function () {
    Order::factory()->create(['grand_total' => 5000]);

    Livewire::withoutLazyLoading()->test(OrderBuyerStatsWidget::class)->assertSee('€0.00');
});
