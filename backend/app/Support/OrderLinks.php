<?php

namespace App\Support;

use App\Models\User;

/**
 * Builds the link to an Order for whoever receives it: Buyers and Suppliers
 * use the Nuxt frontend, in their own language; admins use Filament.
 */
class OrderLinks
{
    public static function forRecipient(User $recipient, int $orderId, int $buyerId): string
    {
        if ($recipient->id === $buyerId) {
            return self::buyer($orderId, $recipient->preferredLocale());
        }

        if ($recipient->isAdmin()) {
            return self::admin($orderId);
        }

        return self::supplier($orderId, $recipient->preferredLocale());
    }

    public static function buyer(int $orderId, ?string $locale = null): string
    {
        return FrontendUrl::to('/orders/'.$orderId, $locale);
    }

    public static function supplier(int $orderId, ?string $locale = null): string
    {
        return FrontendUrl::to('/supplier/orders/'.$orderId, $locale);
    }

    public static function admin(int $orderId): string
    {
        return route('filament.admin.resources.orders.view', $orderId);
    }
}
