<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\AttributeValueMethodEnum;
use App\Enums\ProductVariationTypesEnum;
use App\Enums\RolesEnum;
use App\Models\Attribute;
use App\Models\AttributeOptions;
use App\Models\Product;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()->schema([
                    Section::make('Product Information')->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->readOnly()
                            ->unique(Product::class, 'slug', ignoreRecord: true),
                        MarkdownEditor::make('description')
                            ->columnSpanFull()
                            ->fileAttachmentsDirectory('products/attachments')
                            ->fileAttachmentsDisk('media')
                    ])->columns(2),
                    Section::make('Product Images')->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->collection('product-images')
                            ->disk('media')
                            ->multiple()
                            ->maxFiles(5)
                            ->reorderable()
                    ]),
                    Section::make('Product Variations')
                        ->schema([
                            Repeater::make('productAttributeValues')
                                ->relationship()
                                ->schema([
                                    Select::make('attribute_id')
                                        ->label('Attribute')
                                        ->options(Attribute::query()->whereNotNull('name')->get()->pluck('name', 'id')->map(fn ($name) => __("product-attributes.{$name}"))->toArray())
                                        ->reactive()
                                        ->required(),
                                    Select::make('attribute_option_id')
                                        ->label('Value')
                                        ->options(function (Get $get) {
                                            $attribute = Attribute::find($get('attribute_id'));
                                            if ($attribute) {
                                                return $attribute->attributeOptions()->whereNotNull('option_name')->get()->pluck('option_name', 'id')->map(fn ($name) => __("product-attributes.{$name}"));
                                            }
                                            return AttributeOptions::query()->whereNotNull('option_name')->get()->pluck('option_name', 'id')->map(fn ($name) => __("product-attributes.{$name}"));
                                        })
                                        ->disabled(fn (Get $get) => !$get('attribute_id'))
                                        ->required(),
                                    Select::make('attribute_value_method')
                                        ->label('Value Method')
                                        ->options(AttributeValueMethodEnum::labels())
                                        ->placeholder('Select a method')
                                        ->live(),
                                    TextInput::make('attribute_value')
                                        ->label('Value')
                                        ->disabled(fn (Get $get) => in_array($get('attribute_value_method'), [null, ''])),
                                    Group::make()->schema([
                                        SpatieMediaLibraryFileUpload::make('images')
                                            ->collection('product-attribute-images')
                                            ->disk('media')
                                            ->multiple()
                                            ->maxFiles(10)
                                            ->reorderable()
                                            ->visible(function (Get $get) {
                                                $attribute = Attribute::find($get('attribute_id'));
                                                return $attribute && $attribute->type === ProductVariationTypesEnum::Image->value;
                                            })
                                    ])->columnSpan(4),
                                ])->columns(4)
                        ])
                ])->columnSpan(2),
                Group::make()->schema([
                    Section::make('Price')->schema([
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                    ]),
                    Section::make('Associations')->schema([
                        Select::make('supplier_id')
                            ->visible(fn () => config('features.suppliers_enabled'))
                            ->required(fn () => config('features.suppliers_enabled'))
                            ->searchable()
                            ->preload()
                            ->relationship(
                                'supplier',
                                'name',
                                modifyQueryUsing: fn ($query) => $query->role(RolesEnum::ROLE_SUPPLIER->value),
                            ),
                        Select::make('category_id')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->relationship('category', 'name'),
                        Select::make('brand_id')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->relationship('brand', 'name')
                    ]),
                    Section::make('Status')->schema([
                        Toggle::make('in_stock')
                            ->required()
                            ->default(true),
                        Toggle::make('is_active')
                            ->required()
                            ->default(true),
                        Toggle::make('is_featured')
                            ->required()
                            ->default(false),
                    ])
                ])->columnSpan(1)
            ])->columns(3);
    }
}
