<?php

namespace App\Enums;

/**
 * Something that happens to an Order and may move it to another state (#82).
 * OrderStateMachine decides which events are allowed from which state.
 */
enum OrderEventEnum: string
{
    case Pay = 'pay';
    case FailPayment = 'fail_payment';
    case Expire = 'expire';
    case Ship = 'ship';
    case Deliver = 'deliver';
    case Cancel = 'cancel';
}
