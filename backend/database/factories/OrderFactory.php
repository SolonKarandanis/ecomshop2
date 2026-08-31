<?php

namespace Database\Factories;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'supplier_id'       => User::factory(),
            'grand_total'       => fake()->randomFloat(2, 10, 500),
            'payment_method_id' => PaymentMethod::factory(),
            'payment_status'    => OrderPaymentStatusEnum::PENDING->value,
            'order_status'      => OrderStatusEnum::Draft->value,
            'currency'          => 'usd',
            'shipping_amount'   => 0,
            'shipping_method'   => null,
            'notes'             => null,
        ];
    }
}
