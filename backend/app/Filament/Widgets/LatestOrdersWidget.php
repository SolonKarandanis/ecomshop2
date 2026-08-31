<?php

namespace App\Filament\Widgets;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\ShippingMethodEnum;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrdersWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort=2;

    public function table(Table $table): Table
    {
        return $table
            ->query(OrderResource::getEloquentQuery())
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at','desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Order Id')
                    ->sortable(true),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->numeric()
                    ->sortable()
                    ->money('eur'),
                TextColumn::make('payment_method')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->formatStateUsing(fn ($state) => OrderPaymentStatusEnum::labels()[$state] ?? $state)
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_status')
                    ->formatStateUsing(fn ($state) => OrderStatusEnum::labels()[$state] ?? $state)
                    ->badge()
                    ->color(fn(string $state):string=> match($state){
                        OrderStatusEnum::Draft->value => 'info',
                        OrderStatusEnum::Paid->value => 'warning',
                        OrderStatusEnum::Shipped->value => 'success',
                        OrderStatusEnum::Delivered->value => 'success',
                        OrderStatusEnum::Cancelled->value => 'danger',
                    })
                    ->icon(fn(string $state):string=>match($state){
                        OrderStatusEnum::Draft->value => 'heroicon-m-sparkles',
                        OrderStatusEnum::Paid->value => 'heroicon-m-arrow-path',
                        OrderStatusEnum::Shipped->value => 'heroicon-m-truck',
                        OrderStatusEnum::Delivered->value => 'heroicon-m-check-badge',
                        OrderStatusEnum::Cancelled->value => 'heroicon-m-x-circle',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shipping_method')
                    ->formatStateUsing(fn ($state) => ShippingMethodEnum::labels()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('View Order')
                ->url(fn(Order $record):string=>OrderResource::getUrl('view',['record'=>$record->id]))
                ->icon('heroicon-m-eye')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
