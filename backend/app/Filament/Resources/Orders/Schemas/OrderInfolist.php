<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('grand_total')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('paymentMethod.resource_key')
                    ->label('Payment Method')
                    ->formatStateUsing(fn ($state) => PaymentMethodEnum::labels()[$state] ?? $state)
                    ->placeholder('-'),
                TextEntry::make('payment_status')
                    ->formatStateUsing(fn ($state) => OrderPaymentStatusEnum::labels()[$state] ?? $state)
                    ->placeholder('-'),
                TextEntry::make('order_status')
                    ->formatStateUsing(fn ($state) => OrderStatusEnum::labels()[$state] ?? $state),
                TextEntry::make('currency')
                    ->placeholder('-'),
                TextEntry::make('shipping_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('shipping_method')
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
