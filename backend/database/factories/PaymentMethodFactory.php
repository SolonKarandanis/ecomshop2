<?php

namespace Database\Factories;

use App\Enums\PaymentMethodEnum;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'resource_key' => PaymentMethodEnum::STRIPE->value,
        ];
    }

    public function cashOnDelivery(): static
    {
        return $this->state(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    }
}
