<?php

use App\Enums\PaymentMethodEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        // PayPal has no payment handler yet (PaymentHandlerFactory::make() rejects it).
        DB::table('payment_methods')
            ->where('resource_key', PaymentMethodEnum::PAYPAL->value)
            ->update(['is_active' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
