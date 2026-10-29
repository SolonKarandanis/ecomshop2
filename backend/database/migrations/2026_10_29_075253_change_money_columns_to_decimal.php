<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Prices are exact decimals everywhere else (products, orders); the cart's float columns
// could drift a fraction of a cent before a total was copied into an Order (#70).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->default(0)->change();
            $table->decimal('total_price', 10, 2)->default(0)->change();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->decimal('total_price', 10, 2)->default(0)->change();
        });

        // A fixed amount or a percentage, depending on attribute_value_method.
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->decimal('attribute_value', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->float('unit_price')->default(0)->change();
            $table->float('total_price')->default(0)->change();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->float('total_price')->default(0)->change();
        });

        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->float('attribute_value')->nullable()->change();
        });
    }
};
