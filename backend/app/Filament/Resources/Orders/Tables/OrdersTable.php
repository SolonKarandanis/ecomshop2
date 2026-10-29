<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\ShippingMethodEnum;
use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
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
                // Filament re-checks disabled() on the server before saving, so a
                // terminal Order can't be changed from here even by a forged request.
                SelectColumn::make('order_status')
                    ->options(OrderStatusEnum::labels())
                    ->disabled(fn (Order $record) => OrderStatusEnum::from($record->order_status)->isTerminal())
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
