<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Enums\OrderEventEnum;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\StateMachines\OrderStateMachine;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * The admin's Ship / Deliver / Cancel buttons (#82). Each one is shown only when
 * the transition table allows it for the Order's current state, and goes through
 * OrderStateMachine like every other status change, so OrderObserver still sends
 * the Notifications and the status history still records who made the change.
 */
class OrderTransitionActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::action(OrderEventEnum::Ship, 'Ship', 'heroicon-m-truck', 'success'),
            self::action(OrderEventEnum::Deliver, 'Mark delivered', 'heroicon-m-check-badge', 'success'),
            self::action(OrderEventEnum::Cancel, 'Cancel order', 'heroicon-m-x-circle', 'danger'),
        ];
    }

    private static function action(OrderEventEnum $event, string $label, string $icon, string $color): Action
    {
        return Action::make($event->value)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (Order $record) => app(OrderStateMachine::class)->can($record, $event, auth()->user()))
            ->action(function (Order $record) use ($event) {
                try {
                    app(OrderStateMachine::class)->apply($record, $event, auth()->user());
                } catch (OrderException $exception) {
                    // Someone else moved the Order on since the page loaded.
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title("Order #{$record->id} updated")->send();
            });
    }
}
