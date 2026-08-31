<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatusEnum;
use App\Filament\Resources\Orders\Concerns\ValidatesSingleSupplierItems;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\OrderService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    use ValidatesSingleSupplierItems;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Runs before the Repeater relationship items are persisted (which happens
     * as a side effect of form state resolution, ahead of mutateFormDataBeforeSave).
     */
    protected function beforeSave(): void
    {
        $this->assertSingleSupplier();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $currentStatus = OrderStatusEnum::from($this->getRecord()->order_status);

        if ($currentStatus->isTerminal()) {
            $data['order_status'] = $currentStatus->value;
        }

        $items = $this->form->getRawState()['items'] ?? [];
        $data['supplier_id'] = app(OrderService::class)->resolveSupplierIdForItems($items, auth()->id());

        return $data;
    }
}
