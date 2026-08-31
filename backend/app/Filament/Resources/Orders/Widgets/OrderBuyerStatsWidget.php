<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class OrderBuyerStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = Order::query()
            ->select(
                DB::raw("AVG(grand_total) as average_price")
            )
            ->first();
        return [
            Stat::make('Average Price', Number::currency($stats->average_price ?? 0, 'eur'))
        ];
    }
}
