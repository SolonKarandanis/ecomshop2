<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()->schema([
                    Section::make('Order Information')->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('payment_method_id')
                            ->label('Payment Method')
                            ->relationship('paymentMethod', 'resource_key')
                            ->getOptionLabelFromRecordUsing(fn (PaymentMethod $record) => PaymentMethodEnum::labels()[$record->resource_key] ?? $record->resource_key)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('payment_status')
                            ->options(OrderPaymentStatusEnum::labels())
                            ->default(OrderPaymentStatusEnum::PENDING->value)
                            ->required(),
                        ToggleButtons::make('order_status')
                            ->inline()
                            ->default(OrderStatusEnum::Draft->value)
                            ->required()
                            ->options(OrderStatusEnum::labels())
                            ->colors([
                                OrderStatusEnum::Draft->value => 'info',
                                OrderStatusEnum::Paid->value => 'warning',
                                OrderStatusEnum::Shipped->value => 'success',
                                OrderStatusEnum::Delivered->value => 'success',
                                OrderStatusEnum::Cancelled->value => 'danger',
                            ])
                            ->icons([
                                OrderStatusEnum::Draft->value => 'heroicon-m-sparkles',
                                OrderStatusEnum::Paid->value => 'heroicon-m-arrow-path',
                                OrderStatusEnum::Shipped->value => 'heroicon-m-truck',
                                OrderStatusEnum::Delivered->value => 'heroicon-m-check-badge',
                                OrderStatusEnum::Cancelled->value => 'heroicon-m-x-circle',
                            ])
                            ->disabled(fn (?Order $record) => $record && OrderStatusEnum::from($record->order_status)->isTerminal())
                            ->helperText(fn (?Order $record) => $record && OrderStatusEnum::from($record->order_status)->isTerminal()
                                ? 'This order has reached a terminal status and can no longer be changed.'
                                : null),
                        Select::make('currency')
                            ->options([
                                'usd' => 'USD',
                                'eur' => 'EUR',
                                'gbp' => 'GBP',
                            ])
                            ->default('eur')
                            ->required(),
                        Select::make('shipping_method')
                            ->options([
                                'fedex' => 'FeDex',
                                'ups' => 'UPS',
                                'acs' => 'ACS',
                            ])
                            ->default('acs'),
                        Textarea::make('notes')
                            ->columnSpanFull(),

                    ])->columns(2),
                    Section::make('Order Items')->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpan(4)
                                    ->reactive()
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('unit_amount', Product::find($state)?->price ?? 0))
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('total_amount', Product::find($state)?->price ?? 0)),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->columnSpan(2)
                                    ->reactive()
                                    ->afterStateUpdated(fn (Set $set, ?string $state, Get $get) => $set('total_amount', $state * $get('unit_amount'))),

                                TextInput::make('unit_amount')
                                    ->numeric()
                                    ->required()
                                    ->readOnly()
                                    ->columnSpan(3),
                                TextInput::make('total_amount')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(3),
                            ])->columns(12),
                        TextEntry::make('grand_total_placeholder')
                            ->label('GranD Total')
                            ->state(function (Get $get, Set $set) {
                                if (! $repeaters = $get('items')) {
                                    return 0;
                                }
                                $total = collect($repeaters)->keys()->sum(fn ($key) => $get("items.{$key}.total_amount"));
                                $set('grand_total', $total);

                                return Number::currency($total, 'eur');
                            }),
                        Hidden::make('grand_total')
                            ->default(0),
                    ]),
                ])->columnSpanFull(),
            ]);
    }
}
