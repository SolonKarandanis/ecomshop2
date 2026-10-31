<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Every status, so none is left off the dashboard (Delivered used to be).
        $statuses = OrderStatusEnum::cases();

        $counts = Order::query()
            ->whereIn('order_status', array_map(fn (OrderStatusEnum $status) => $status->value, $statuses))
            ->selectRaw('order_status, COUNT(*) as aggregate')
            ->groupBy('order_status')
            ->pluck('aggregate', 'order_status');

        $labels = OrderStatusEnum::labels();

        return array_map(
            fn (OrderStatusEnum $status) => Stat::make($labels[$status->value], $counts[$status->value] ?? 0),
            $statuses
        );
    }
}
