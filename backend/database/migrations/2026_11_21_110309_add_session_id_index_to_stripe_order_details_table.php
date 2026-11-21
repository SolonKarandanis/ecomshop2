<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Stripe webhook event and /success redirect finds its Order by Checkout
 * Session id (OrderRepository::getOrderByStripeSessionId). Without an index that
 * lookup scans the whole table, which grows with every Stripe Order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stripe_order_details', function (Blueprint $table) {
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('stripe_order_details', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
        });
    }
};
