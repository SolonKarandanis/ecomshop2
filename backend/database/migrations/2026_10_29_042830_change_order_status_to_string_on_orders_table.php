<?php

use App\Enums\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The original ENUM never matched OrderStatusEnum's values; MySQL/MariaDB reject them.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_status')->default(OrderStatusEnum::Draft->value)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('order_status', ['new', 'processing', 'shipped', 'delivered', 'cancelled'])->default('new')->change();
        });
    }
};
