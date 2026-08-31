<?php

namespace Database\Factories;

use App\Enums\PaymentMethodEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'resource_key' => PaymentMethodEnum::STRIPE->value,
        ];
    }
}
