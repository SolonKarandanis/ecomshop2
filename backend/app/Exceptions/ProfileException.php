<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class ProfileException extends \Exception
{
    public static function emailTaken(): ProfileException
    {
        return new self(__('messages.update_profile.email_taken'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function wrongCurrentPassword(): ProfileException
    {
        return new self(__('messages.change_password.wrong_current'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }
}
