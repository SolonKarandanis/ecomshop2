<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class CartException extends \Exception
{
    public static function saveCart(): CartException
    {
        return new self(__('messages.cart_exceptions.save_cart'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function updateItems(): CartException
    {
        return new self(__('messages.cart_exceptions.update_items'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function deleteItems(): CartException
    {
        return new self(__('messages.cart_exceptions.delete_items'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function clearCart(): CartException
    {
        return new self(__('messages.cart_exceptions.clear_cart'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function supplierMismatch(): CartException
    {
        return new self(__('messages.cart_exceptions.supplier_mismatch'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }
}
