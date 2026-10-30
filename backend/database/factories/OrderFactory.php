<?php

namespace Database\Factories;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'supplier_id' => User::factory(),
            'grand_total' => fake()->randomFloat(2, 10, 500),
            'payment_method_id' => PaymentMethod::factory(),
            'payment_status' => OrderPaymentStatusEnum::PENDING->value,
            'order_status' => OrderStatusEnum::Draft->value,
            'currency' => 'usd',
            'shipping_amount' => 0,
            'shipping_method' => null,
            'notes' => null,
        ];
    }

    /**
     * A Stripe Order whose payment went through (the state the webhook leaves it in).
     */
    public function paid(): static
    {
        return $this->state([
            'order_status' => OrderStatusEnum::Paid->value,
            'payment_status' => OrderPaymentStatusEnum::PAID->value,
        ]);
    }

    public function shipped(): static
    {
        return $this->state([
            'order_status' => OrderStatusEnum::Shipped->value,
            'payment_status' => OrderPaymentStatusEnum::PAID->value,
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'order_status' => OrderStatusEnum::Delivered->value,
            'payment_status' => OrderPaymentStatusEnum::PAID->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'order_status' => OrderStatusEnum::Cancelled->value,
            'payment_status' => OrderPaymentStatusEnum::FAILED->value,
        ]);
    }

    public function cashOnDelivery(): static
    {
        return $this->state(['payment_method_id' => PaymentMethod::factory()->cashOnDelivery()]);
    }
}
