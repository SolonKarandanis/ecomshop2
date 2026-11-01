<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\TranslatableFields;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! config('features.suppliers_enabled')) {
            $data['supplier_id'] = auth()->id();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        [$data] = TranslatableFields::withoutBlankLocales($data);

        return parent::handleRecordCreation($data);
    }
}
