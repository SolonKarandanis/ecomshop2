<?php

namespace App\Enums;

/**
 * Who triggers an Order event: the system (Stripe webhook, success redirect),
 * the Order's own Supplier, or an Admin.
 */
enum OrderActorEnum: string
{
    case System = 'system';
    case Supplier = 'supplier';
    case Admin = 'admin';
}
