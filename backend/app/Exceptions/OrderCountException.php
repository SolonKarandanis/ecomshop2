<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;
use Exception;

class OrderCountException extends Exception
{
    public static function limitExceeded(int $count): OrderCountException
    {
        return new self(
            __('messages.export_orders.limit_error', ['count' => number_format($count)]),
            HttpStatusCodeEnum::BAD_REQUEST->value
        );
    }
}
