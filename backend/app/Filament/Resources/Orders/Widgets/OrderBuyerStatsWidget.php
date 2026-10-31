<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class OrderBuyerStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Only Orders that actually sold: paid (a Stripe payment, or COD cash
        // collected on delivery) and not cancelled afterwards. Unpaid drafts and
        // cancelled Orders would skew the figure.
        $average = Order::query()
            ->where('payment_status', OrderPaymentStatusEnum::PAID->value)
            ->where('order_status', '!=', OrderStatusEnum::Cancelled->value)
            ->avg('grand_total');

        return [
            Stat::make('Average order value', Number::currency((float) ($average ?? 0), 'eur'))
                ->description('Paid orders, excluding cancelled'),
        ];
    }
}
