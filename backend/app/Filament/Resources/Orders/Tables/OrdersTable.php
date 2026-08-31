<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\ShippingMethodEnum;
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
                SelectColumn::make('order_status')
                    ->options(OrderStatusEnum::labels())
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
