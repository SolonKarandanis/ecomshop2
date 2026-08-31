<?php

namespace App\Listeners;

use App\Services\CartService;
use Illuminate\Auth\Events\Login;

readonly class TransferGuestCartToUser
{
    /**
     * Create the event listener.
     */
    public function __construct(private CartService $cartService)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if ($event->user->isBuyer()) {
            $this->cartService->moveCartItemsToDatabase();
        }
    }
}
