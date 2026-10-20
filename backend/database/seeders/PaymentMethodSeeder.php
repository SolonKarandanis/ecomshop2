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
        // is_active only applies when the row is created, so re-seeding on every
        // deploy (ADR-0001) never overrides a value an admin changed in Filament.
        // PayPal starts off: it has no payment handler yet.
        $methods = [
            'payment.method.cod' => true,
            'payment.method.stripe' => true,
            'payment.method.paypal' => false,
        ];

        foreach ($methods as $resourceKey => $isActive) {
            PaymentMethod::firstOrCreate(['resource_key' => $resourceKey], ['is_active' => $isActive]);
        }
    }
}
