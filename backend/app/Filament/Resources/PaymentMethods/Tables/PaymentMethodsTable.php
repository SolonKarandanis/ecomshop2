<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('resource_key')
                    ->label('Payment method')
                    ->formatStateUsing(fn (string $state): string => __($state)),
                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('id');
    }
}
