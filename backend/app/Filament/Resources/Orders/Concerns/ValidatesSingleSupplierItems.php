<?php

namespace App\Filament\Resources\Orders\Concerns;

use App\Exceptions\OrderException;
use App\Services\OrderService;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

trait ValidatesSingleSupplierItems
{
    /**
     * @throws Halt
     */
    protected function assertSingleSupplier(): void
    {
        $items = $this->form->getRawState()['items'] ?? [];

        try {
            app(OrderService::class)->assertSingleSupplierForItems($items);
        } catch (OrderException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            throw new Halt;
        }
    }
}
