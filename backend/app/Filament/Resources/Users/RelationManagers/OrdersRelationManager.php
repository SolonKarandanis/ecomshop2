<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\OrderStatusEnum;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('order_status')
                    ->formatStateUsing(fn ($state) => OrderStatusEnum::labels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        OrderStatusEnum::Draft->value => 'info',
                        OrderStatusEnum::Paid->value => 'warning',
                        OrderStatusEnum::Shipped->value => 'success',
                        OrderStatusEnum::Delivered->value => 'success',
                        OrderStatusEnum::Cancelled->value => 'danger',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        OrderStatusEnum::Draft->value => 'heroicon-m-sparkles',
                        OrderStatusEnum::Paid->value => 'heroicon-m-arrow-path',
                        OrderStatusEnum::Shipped->value => 'heroicon-m-truck',
                        OrderStatusEnum::Delivered->value => 'heroicon-m-check-badge',
                        OrderStatusEnum::Cancelled->value => 'heroicon-m-x-circle',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('grand_total')
                    ->state(fn (Order $record) => $record->grand_total?->toFloat())
                    ->numeric()
                    ->sortable()
                    ->money('eur'),
                TextColumn::make('payment_method')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('shipping_amount')
                    ->state(fn (Order $record) => $record->shipping_amount?->toFloat())
                    ->numeric()
                    ->sortable(),
                TextColumn::make('shipping_method')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Order date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            // Orders aren't created or edited here: the Orders resource validates
            // single-Supplier items and changes statuses only through the state
            // machine (#82), so each row links to the Order's own page instead.
            ->headerActions([
                AssociateAction::make(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
