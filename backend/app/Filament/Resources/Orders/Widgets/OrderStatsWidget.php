<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OrderStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = Order::query()
            ->select(
                DB::raw("COUNT(CASE WHEN order_status = 'new' THEN 1 END) as new_orders"),
                DB::raw("COUNT(CASE WHEN order_status = 'processing' THEN 1 END) as processing_orders"),
                DB::raw("COUNT(CASE WHEN order_status = 'shipped' THEN 1 END) as shipped_orders"),
                DB::raw("COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders")
            )
            ->first();

        return [
            Stat::make('New Orders', $stats->new_orders ?? 0),
            Stat::make('Orders Processing', $stats->processing_orders ?? 0),
            Stat::make('Orders Shipped', $stats->shipped_orders ?? 0),
            Stat::make('Orders Cancelled', $stats->cancelled_orders ?? 0)
        ];
    }
}
