<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            ['resource_key' => 'payment.method.cod'],
            ['resource_key' => 'payment.method.stripe'],
            ['resource_key' => 'payment.method.paypal'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate($method);
        }
    }
}
