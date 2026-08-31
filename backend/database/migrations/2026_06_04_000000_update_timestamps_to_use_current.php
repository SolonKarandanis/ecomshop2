<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'categories',
        'brands',
        'products',
        'orders',
        'order_items',
        'addresses',
        'carts',
        'cart_items',
        'stripe_order_details',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('created_at')->useCurrent()->nullable(false)->change();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('created_at')->nullable()->default(null)->change();
                $table->timestamp('updated_at')->nullable()->default(null)->change();
            });
        }
    }
};
