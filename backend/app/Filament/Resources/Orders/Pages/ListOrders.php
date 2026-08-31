<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatusEnum;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Widgets\OrderBuyerStatsWidget;
use App\Filament\Resources\Orders\Widgets\OrderStatsWidget;
use App\Models\Order;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array{
        return [
            OrderStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
          OrderBuyerStatsWidget::class,
        ];
    }

    public function getTabs(): array{
        return [
            null=>Tab::make('All'),
            'draft'=>Tab::make(OrderStatusEnum::labels()[OrderStatusEnum::Draft->value])
                ->query(fn($query)=>$query->where('order_status', OrderStatusEnum::Draft->value)),
            'paid'=>Tab::make(OrderStatusEnum::labels()[OrderStatusEnum::Paid->value])
                ->query(fn($query)=>$query->where('order_status', OrderStatusEnum::Paid->value)),
            'shipped'=>Tab::make(OrderStatusEnum::labels()[OrderStatusEnum::Shipped->value])
                ->query(fn($query)=>$query->where('order_status',OrderStatusEnum::Shipped->value)),
            'delivered'=>Tab::make(OrderStatusEnum::labels()[OrderStatusEnum::Delivered->value])
                ->query(fn($query)=>$query->where('order_status',OrderStatusEnum::Delivered->value)),
            'cancelled'=>Tab::make(OrderStatusEnum::labels()[OrderStatusEnum::Cancelled->value])
                ->query(fn($query)=>$query->where('order_status', OrderStatusEnum::Cancelled->value)),
        ];
    }
}
