<?php

namespace App\Support;

use App\Models\User;

/**
 * Builds the link to an Order for whoever receives it: Buyers and Suppliers
 * use the Nuxt frontend, admins use Filament.
 */
class OrderLinks
{
    public static function forRecipient(User $recipient, int $orderId, int $buyerId): string
    {
        if ($recipient->id === $buyerId) {
            return self::buyer($orderId);
        }

        if ($recipient->isAdmin()) {
            return self::admin($orderId);
        }

        return self::supplier($orderId);
    }

    public static function buyer(int $orderId): string
    {
        return config('app.frontend_url').'/orders/'.$orderId;
    }

    public static function supplier(int $orderId): string
    {
        return config('app.frontend_url').'/supplier/orders/'.$orderId;
    }

    public static function admin(int $orderId): string
    {
        return route('filament.admin.resources.orders.view', $orderId);
    }
}
