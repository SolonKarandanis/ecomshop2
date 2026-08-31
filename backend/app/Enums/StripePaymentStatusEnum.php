<?php

namespace App\Enums;

enum StripePaymentStatusEnum:string
{
    case PENDING = 'pending';
    case PAID = 'paid';
}
