<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\OrderStatusEnum;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('grand_total')
                    ->numeric(),
                TextInput::make('payment_method'),
                TextInput::make('payment_status'),
                Select::make('order_status')
                    ->options(OrderStatusEnum::labels())
                    ->required()
                    ->default(OrderStatusEnum::Draft->value),
                TextInput::make('currency'),
                TextInput::make('shipping_amount')
                    ->numeric(),
                TextInput::make('shipping_method'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

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
                    ->color(fn(string $state)=> match ($state) {
                        OrderStatusEnum::Draft->value => 'info',
                        OrderStatusEnum::Paid->value => 'warning',
                        OrderStatusEnum::Shipped->value => 'success',
                        OrderStatusEnum::Delivered->value => 'success',
                        OrderStatusEnum::Cancelled->value => 'danger',
                    })
                    ->icon(fn(string $state)=>match ($state) {
                        OrderStatusEnum::Draft->value => 'heroicon-m-sparkles',
                        OrderStatusEnum::Paid->value => 'heroicon-m-arrow-path',
                        OrderStatusEnum::Shipped->value => 'heroicon-m-truck',
                        OrderStatusEnum::Delivered->value => 'heroicon-m-check-badge',
                        OrderStatusEnum::Cancelled->value => 'heroicon-m-x-circle',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('grand_total')
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
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
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
