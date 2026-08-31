<?php

namespace App\Filament\Resources\AttributeOptions\Schemas;

use App\Models\Attribute;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AttributeOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('attribute_id')
                    ->label('Attribute')
                    ->options(Attribute::all()->pluck('name', 'id')->toArray())
                    ->required(),
                TextInput::make('name')
                    ->required()
                    -maxLength(255),
            ]);
    }
}
