<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\TranslatableFields;
use App\Models\Category;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    // A name per language (#81); the slug, shared by every
                    // language, follows the first one filled in.
                    TranslatableFields::tabs(fn (string $locale) => [
                        TranslatableFields::name($locale)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('slug', Str::slug(TranslatableFields::firstName($get)))),
                    ]),
                    Grid::make()->schema([
                        TextInput::make('slug')
                            ->required()
                            ->readOnly()
                            ->maxLength(255)
                            ->unique(Category::class, 'slug', ignoreRecord: true),
                    ]),
                    SpatieMediaLibraryFileUpload::make('image')
                        ->collection('category-images')
                        ->disk('media'),
                    Toggle::make('is_active')
                        ->required()
                        ->default(true),
                ])->columnSpanFull(),
            ]);
    }
}
