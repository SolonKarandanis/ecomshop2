<?php

namespace App\StateMachines;

use App\Enums\OrderActorEnum;
use App\Enums\OrderEventEnum;
use App\Enums\OrderPaymentStatusEnum as Payment;
use App\Enums\OrderStatusEnum as Status;
use App\Enums\PaymentMethodEnum as Method;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;

/**
 * The single owner of every change to an Order's order_status and payment_status
 * (#82). The transition table below is the only place the allowed moves are
 * defined; apply() is the only code that writes the statuses after an Order is
 * created. Side effects (Notifications, status history) stay in OrderObserver and
 * OrderStatusHistoryObserver, which react to the change after it commits.
 */
#[Singleton]
class OrderStateMachine
{
    /** @var list<OrderTransition> */
    private readonly array $transitions;

    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {
        $system = [OrderActorEnum::System];
        $staff = [OrderActorEnum::Supplier, OrderActorEnum::Admin];

        $this->transitions = [
            // Stripe: the webhook and the success redirect finalize the payment (ADR-0004).
            new OrderTransition(OrderEventEnum::Pay, Status::Draft, Payment::PENDING, Method::STRIPE, Status::Paid, Payment::PAID, $system),
            new OrderTransition(OrderEventEnum::FailPayment, Status::Draft, Payment::PENDING, Method::STRIPE, Status::Draft, Payment::FAILED, $system),
            new OrderTransition(OrderEventEnum::Expire, Status::Draft, Payment::PENDING, Method::STRIPE, Status::Cancelled, Payment::FAILED, $system),
            new OrderTransition(OrderEventEnum::Ship, Status::Paid, Payment::PAID, Method::STRIPE, Status::Shipped, Payment::PAID, $staff),
            new OrderTransition(OrderEventEnum::Deliver, Status::Shipped, Payment::PAID, Method::STRIPE, Status::Delivered, Payment::PAID, $staff),
            new OrderTransition(OrderEventEnum::Cancel, Status::Paid, Payment::PAID, Method::STRIPE, Status::Cancelled, Payment::PAID, $staff),
            // Cash on Delivery: shippable once placed, paid when the cash is collected on delivery.
            new OrderTransition(OrderEventEnum::Ship, Status::Draft, Payment::PENDING, Method::CASH_ON_DELIVERY, Status::Shipped, Payment::PENDING, $staff),
            new OrderTransition(OrderEventEnum::Deliver, Status::Shipped, Payment::PENDING, Method::CASH_ON_DELIVERY, Status::Delivered, Payment::PAID, $staff),
            new OrderTransition(OrderEventEnum::Cancel, Status::Draft, Payment::PENDING, Method::CASH_ON_DELIVERY, Status::Cancelled, Payment::PENDING, $staff),
        ];
    }

    /**
     * @return list<OrderTransition>
     */
    public function transitions(): array
    {
        return $this->transitions;
    }

    public function can(Order $order, OrderEventEnum $event, ?User $actor): bool
    {
        return $this->transitionFor($order, $event)?->allows($this->actorFor($order, $actor)) ?? false;
    }

    /**
     * @return list<OrderEventEnum>
     */
    public function availableEvents(Order $order, ?User $actor): array
    {
        return array_values(array_filter(
            OrderEventEnum::cases(),
            fn (OrderEventEnum $event) => $this->can($order, $event, $actor),
        ));
    }

    /**
     * Applies $event to the Order, or throws if the table doesn't allow it from the
     * Order's current state for this actor. A null $actor is the system.
     *
     * @throws OrderException
     */
    public function apply(Order $order, OrderEventEnum $event, ?User $actor): Order
    {
        return $this->run($order, $event, $actor)
            ?? throw OrderException::transitionNotAllowed();
    }

    /**
     * Like apply(), but returns null instead of throwing when the event isn't
     * allowed. For the Stripe paths: the webhook and the success redirect race, and
     * Stripe retries, so the same event routinely arrives for an Order that has
     * already moved on (ADR-0004).
     */
    public function applyIfAllowed(Order $order, OrderEventEnum $event, ?User $actor): ?Order
    {
        return $this->run($order, $event, $actor);
    }

    /**
     * The transition table as a Mermaid state diagram, for docs and review.
     */
    public function toMermaid(): string
    {
        $lines = ['stateDiagram-v2', '    [*] --> Draft'];

        foreach ($this->transitions as $transition) {
            $method = str($transition->paymentMethod->value)->afterLast('.');
            $lines[] = "    {$transition->fromStatus->name} --> {$transition->toStatus->name}: {$transition->event->value} ({$method})";
        }

        foreach (Status::cases() as $status) {
            if ($status->isTerminal()) {
                $lines[] = "    {$status->name} --> [*]";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Locks the row and re-reads it before checking the table, so two concurrent
     * events (e.g. the webhook and the redirect, or a double-clicked action) can't
     * both apply from the same starting state.
     */
    private function run(Order $order, OrderEventEnum $event, ?User $actor): ?Order
    {
        return DB::transaction(function () use ($order, $event, $actor) {
            $locked = $this->orderRepository->lockOrderById($order->id);
            $transition = $this->transitionFor($locked, $event);

            if ($transition === null || ! $transition->allows($this->actorFor($locked, $actor))) {
                return null;
            }

            $locked->order_status = $transition->toStatus->value;
            $locked->payment_status = $transition->toPayment->value;
            $this->orderRepository->updateOrder($locked);

            return $locked;
        });
    }

    private function transitionFor(Order $order, OrderEventEnum $event): ?OrderTransition
    {
        $paymentMethod = $order->paymentMethod?->resource_key;

        foreach ($this->transitions as $transition) {
            if ($transition->appliesTo($event, $order->order_status, $order->payment_status, $paymentMethod)) {
                return $transition;
            }
        }

        return null;
    }

    private function actorFor(Order $order, ?User $user): ?OrderActorEnum
    {
        return match (true) {
            $user === null => OrderActorEnum::System,
            $user->isAdmin() => OrderActorEnum::Admin,
            $user->isSupplier() && $order->supplier_id === $user->id => OrderActorEnum::Supplier,
            default => null,
        };
    }
}
