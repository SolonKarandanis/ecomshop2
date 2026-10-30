<?php

namespace App\StateMachines;

use App\Enums\OrderActorEnum;
use App\Enums\OrderEventEnum;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\PaymentMethodEnum;

/**
 * One row of the Order transition table: an event, the state it applies from
 * (order status, payment status and payment method), the state it moves to, and
 * who may trigger it.
 */
final readonly class OrderTransition
{
    /**
     * @param  list<OrderActorEnum>  $actors
     */
    public function __construct(
        public OrderEventEnum $event,
        public OrderStatusEnum $fromStatus,
        public OrderPaymentStatusEnum $fromPayment,
        public PaymentMethodEnum $paymentMethod,
        public OrderStatusEnum $toStatus,
        public OrderPaymentStatusEnum $toPayment,
        public array $actors,
    ) {}

    public function appliesTo(OrderEventEnum $event, string $orderStatus, ?string $paymentStatus, ?string $paymentMethod): bool
    {
        return $this->event === $event
            && $this->fromStatus->value === $orderStatus
            && $this->fromPayment->value === $paymentStatus
            && $this->paymentMethod->value === $paymentMethod;
    }

    public function allows(?OrderActorEnum $actor): bool
    {
        return $actor !== null && in_array($actor, $this->actors, true);
    }
}
