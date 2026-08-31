<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Concerns\ValidatesSingleSupplierItems;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\OrderService;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    use ValidatesSingleSupplierItems;

    protected static string $resource = OrderResource::class;

    protected function beforeCreate(): void
    {
        $this->assertSingleSupplier();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $items = $this->form->getRawState()['items'] ?? [];
        $data['supplier_id'] = app(OrderService::class)->resolveSupplierIdForItems($items, auth()->id());

        return $data;
    }
}
