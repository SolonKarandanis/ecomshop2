<?php

namespace App\Enums;

enum ReviewStatusEnum: string
{

    case PUBLISHED = 'review.status.published';
    case HIDDEN = 'review.status.hidden';

    public static function values(): array
    {
        return [
            self::PUBLISHED->value,
            self::HIDDEN->value,
        ];
    }

    public static function labels(): array
    {
        return [
            self::PUBLISHED->value => __('review.status.published'),
            self::HIDDEN->value => __('review.status.hidden'),
        ];
    }

    public static function colors(): array
    {
        return [
            'success' => self::PUBLISHED->value,
            'danger'  => self::HIDDEN->value,
        ];
    }
}
