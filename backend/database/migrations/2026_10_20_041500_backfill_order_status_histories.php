<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give every Order that has no history one entry with its current statuses
     * at its created_at, so no Order has an empty history. Who made the
     * earlier changes is unknown, so changed_by stays null.
     */
    public function up(): void
    {
        DB::table('order_status_histories')->insertUsing(
            ['order_id', 'order_status', 'payment_status', 'changed_by', 'created_at'],
            DB::table('orders')
                ->select('id', 'order_status', 'payment_status', DB::raw('NULL'), 'created_at')
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('order_status_histories')
                    ->whereColumn('order_status_histories.order_id', 'orders.id')),
        );
    }

    /**
     * Nothing to undo: dropping the table (the previous migration's down()) removes the entries.
     */
    public function down(): void {}
};
