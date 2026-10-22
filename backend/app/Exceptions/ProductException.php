<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class ProductException extends \Exception
{
    public static function orderedProductCannotBeDeleted(): ProductException
    {
        return new self(__('messages.product_exceptions.ordered_cannot_be_deleted'), HttpStatusCodeEnum::CONFLICT->value);
    }
}
