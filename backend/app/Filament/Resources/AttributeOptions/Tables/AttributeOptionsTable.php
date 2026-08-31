<?php

namespace App\Filament\Resources\AttributeOptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttributeOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attribute.name')
                    ->formatStateUsing(fn (string $state): string => __("product-attributes.{$state}"))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('option_name')
                    ->formatStateUsing(fn (string $state): string => __("product-attributes.{$state}"))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
