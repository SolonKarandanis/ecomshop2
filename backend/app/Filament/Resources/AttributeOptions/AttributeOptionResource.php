<?php

namespace App\Filament\Resources\AttributeOptions;

use App\Filament\Resources\AttributeOptions\Pages\CreateAttributeOption;
use App\Filament\Resources\AttributeOptions\Pages\EditAttributeOption;
use App\Filament\Resources\AttributeOptions\Pages\ListAttributeOptions;
use App\Filament\Resources\AttributeOptions\Schemas\AttributeOptionForm;
use App\Filament\Resources\AttributeOptions\Tables\AttributeOptionsTable;
use App\Models\AttributeOptions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttributeOptionResource extends Resource
{
    protected static ?string $model = AttributeOptions::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    public static function form(Schema $schema): Schema
    {
        return AttributeOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttributeOptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributeOptions::route('/'),
            'create' => CreateAttributeOption::route('/create'),
            'edit' => EditAttributeOption::route('/{record}/edit'),
        ];
    }
}
