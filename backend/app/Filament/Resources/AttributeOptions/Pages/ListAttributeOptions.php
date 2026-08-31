<?php

namespace App\Filament\Resources\AttributeOptions\Pages;

use App\Filament\Resources\AttributeOptions\AttributeOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttributeOptions extends ListRecords
{
    protected static string $resource = AttributeOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
