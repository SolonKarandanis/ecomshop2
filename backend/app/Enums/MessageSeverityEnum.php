<?php

namespace App\Enums;

enum MessageSeverityEnum: string
{
    case SUCCESS = 'success';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';

}
