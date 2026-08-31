<?php

namespace App\Filament\Resources\Attributes\Schemas;

use App\Enums\ProductVariationTypesEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->options(ProductVariationTypesEnum::labels())
                    ->required(),
            ]);
    }
}
