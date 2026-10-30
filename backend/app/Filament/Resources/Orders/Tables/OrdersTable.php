<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\ShippingMethodEnum;
use App\Filament\Resources\Orders\Actions\OrderTransitionActions;
use App\Models\Order;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->state(fn (Order $record) => $record->grand_total?->toFloat())
                    ->numeric()
                    ->sortable()
                    ->money('eur'),
                TextColumn::make('paymentMethod.resource_key')
                    ->label('Payment Method')
                    ->formatStateUsing(fn ($state) => PaymentMethodEnum::labels()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->formatStateUsing(fn ($state) => OrderPaymentStatusEnum::labels()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),
                // Read-only: status changes go through the Ship/Deliver/Cancel actions,
                // which only offer what the transition table allows (#82).
                TextColumn::make('order_status')
                    ->formatStateUsing(fn ($state) => OrderStatusEnum::labels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        OrderStatusEnum::Draft->value => 'info',
                        OrderStatusEnum::Paid->value => 'warning',
                        OrderStatusEnum::Shipped->value, OrderStatusEnum::Delivered->value => 'success',
                        OrderStatusEnum::Cancelled->value => 'danger',
                        default => 'gray',
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make(OrderTransitionActions::make())
                    ->label('Status')
                    ->icon('heroicon-m-arrow-right-circle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
