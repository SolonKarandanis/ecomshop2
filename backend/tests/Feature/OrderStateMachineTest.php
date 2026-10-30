<?php

use App\Enums\OrderActorEnum;
use App\Enums\OrderEventEnum;
use App\Enums\OrderPaymentStatusEnum as Payment;
use App\Enums\OrderStatusEnum as Status;
use App\Enums\PaymentMethodEnum;
use App\Enums\RolesEnum;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\StateMachines\OrderStateMachine;
use Spatie\Permission\Models\Role;

function stateMachineUser(RolesEnum $role): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * The transition table (#82), written out independently of OrderStateMachine so the
 * test catches a wrong entry: [event, from order, from payment, method, to order, to payment, actors].
 */
function expectedTransitions(): array
{
    $cod = PaymentMethodEnum::CASH_ON_DELIVERY;
    $stripe = PaymentMethodEnum::STRIPE;
    $system = [OrderActorEnum::System];
    $staff = [OrderActorEnum::Supplier, OrderActorEnum::Admin];

    return [
        [OrderEventEnum::Pay, Status::Draft, Payment::PENDING, $stripe, Status::Paid, Payment::PAID, $system],
        [OrderEventEnum::FailPayment, Status::Draft, Payment::PENDING, $stripe, Status::Draft, Payment::FAILED, $system],
        [OrderEventEnum::Expire, Status::Draft, Payment::PENDING, $stripe, Status::Cancelled, Payment::FAILED, $system],
        [OrderEventEnum::Ship, Status::Paid, Payment::PAID, $stripe, Status::Shipped, Payment::PAID, $staff],
        [OrderEventEnum::Ship, Status::Draft, Payment::PENDING, $cod, Status::Shipped, Payment::PENDING, $staff],
        [OrderEventEnum::Deliver, Status::Shipped, Payment::PAID, $stripe, Status::Delivered, Payment::PAID, $staff],
        [OrderEventEnum::Deliver, Status::Shipped, Payment::PENDING, $cod, Status::Delivered, Payment::PAID, $staff],
        [OrderEventEnum::Cancel, Status::Paid, Payment::PAID, $stripe, Status::Cancelled, Payment::PAID, $staff],
        [OrderEventEnum::Cancel, Status::Draft, Payment::PENDING, $cod, Status::Cancelled, Payment::PENDING, $staff],
    ];
}

function findExpected(OrderEventEnum $event, Status $order, Payment $payment, PaymentMethodEnum $method): ?array
{
    foreach (expectedTransitions() as $row) {
        if ($row[0] === $event && $row[1] === $order && $row[2] === $payment && $row[3] === $method) {
            return $row;
        }
    }

    return null;
}

/**
 * Every combination of state, payment method, event and actor: the allowed ones
 * must write exactly the expected statuses, and everything else must throw and
 * change nothing.
 */
function everyCombination(): Generator
{
    foreach (Status::cases() as $order) {
        foreach (Payment::cases() as $payment) {
            foreach ([PaymentMethodEnum::CASH_ON_DELIVERY, PaymentMethodEnum::STRIPE] as $method) {
                foreach (OrderEventEnum::cases() as $event) {
                    foreach (OrderActorEnum::cases() as $actor) {
                        yield "{$event->value} from {$order->name}/{$payment->name} ({$method->name}) by {$actor->value}" => [$event, $order, $payment, $method, $actor];
                    }
                }
            }
        }
    }
}

function orderInState(Status $order, Payment $payment, PaymentMethodEnum $method, ?User $supplier = null): Order
{
    return Order::factory()->create([
        'order_status' => $order->value,
        'payment_status' => $payment->value,
        'payment_method_id' => PaymentMethod::factory()->create(['resource_key' => $method->value])->id,
        'supplier_id' => $supplier?->id ?? User::factory(),
    ]);
}

it('applies exactly the transitions in the table, for exactly the allowed actors', function (
    OrderEventEnum $event, Status $from, Payment $fromPayment, PaymentMethodEnum $method, OrderActorEnum $actorType,
) {
    $supplier = stateMachineUser(RolesEnum::ROLE_SUPPLIER);
    $actor = match ($actorType) {
        OrderActorEnum::System => null,
        OrderActorEnum::Supplier => $supplier,
        OrderActorEnum::Admin => stateMachineUser(RolesEnum::ROLE_ADMIN),
    };
    $order = orderInState($from, $fromPayment, $method, $supplier);
    $machine = app(OrderStateMachine::class);
    $expected = findExpected($event, $from, $fromPayment, $method);
    $allowed = $expected !== null && in_array($actorType, $expected[6], true);

    expect($machine->can($order, $event, $actor))->toBe($allowed);

    if ($allowed) {
        $result = $machine->apply($order, $event, $actor);

        expect($result->order_status)->toBe($expected[4]->value)
            ->and($result->payment_status)->toBe($expected[5]->value);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'order_status' => $expected[4]->value, 'payment_status' => $expected[5]->value,
        ]);

        return;
    }

    expect(fn () => $machine->apply($order, $event, $actor))->toThrow(OrderException::class);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id, 'order_status' => $from->value, 'payment_status' => $fromPayment->value,
    ]);
})->with(everyCombination());

it("doesn't let a Supplier act on an Order that belongs to another Supplier", function () {
    $order = orderInState(Status::Paid, Payment::PAID, PaymentMethodEnum::STRIPE);
    $otherSupplier = stateMachineUser(RolesEnum::ROLE_SUPPLIER);

    expect(app(OrderStateMachine::class)->can($order, OrderEventEnum::Ship, $otherSupplier))->toBeFalse()
        ->and(fn () => app(OrderStateMachine::class)->apply($order, OrderEventEnum::Ship, $otherSupplier))
        ->toThrow(OrderException::class);
});

it("doesn't let a Buyer apply any event, even to their own Order", function (OrderEventEnum $event) {
    $buyer = stateMachineUser(RolesEnum::ROLE_BUYER);
    $order = orderInState(Status::Paid, Payment::PAID, PaymentMethodEnum::STRIPE);
    $order->update(['user_id' => $buyer->id]);

    expect(app(OrderStateMachine::class)->can($order, $event, $buyer))->toBeFalse();
})->with(OrderEventEnum::cases());

it('lists the events an actor may apply to an Order', function () {
    $supplier = stateMachineUser(RolesEnum::ROLE_SUPPLIER);
    $machine = app(OrderStateMachine::class);

    expect($machine->availableEvents(orderInState(Status::Paid, Payment::PAID, PaymentMethodEnum::STRIPE, $supplier), $supplier))
        ->toBe([OrderEventEnum::Ship, OrderEventEnum::Cancel])
        ->and($machine->availableEvents(orderInState(Status::Draft, Payment::PENDING, PaymentMethodEnum::CASH_ON_DELIVERY, $supplier), $supplier))
        ->toBe([OrderEventEnum::Ship, OrderEventEnum::Cancel])
        ->and($machine->availableEvents(orderInState(Status::Draft, Payment::PENDING, PaymentMethodEnum::STRIPE, $supplier), $supplier))
        ->toBe([])
        ->and($machine->availableEvents(orderInState(Status::Delivered, Payment::PAID, PaymentMethodEnum::STRIPE, $supplier), $supplier))
        ->toBe([]);
});

it('treats a repeated Stripe event as a no-op rather than an error', function () {
    $order = orderInState(Status::Draft, Payment::PENDING, PaymentMethodEnum::STRIPE);
    $machine = app(OrderStateMachine::class);

    expect($machine->applyIfAllowed($order, OrderEventEnum::Pay, null))->not->toBeNull()
        ->and($machine->applyIfAllowed($order, OrderEventEnum::Pay, null))->toBeNull();

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => Status::Paid->value]);
});

it('records one status history entry per transition', function () {
    $supplier = stateMachineUser(RolesEnum::ROLE_SUPPLIER);
    $order = orderInState(Status::Draft, Payment::PENDING, PaymentMethodEnum::CASH_ON_DELIVERY, $supplier);
    $machine = app(OrderStateMachine::class);

    $machine->apply($order, OrderEventEnum::Ship, $supplier);
    $machine->apply($order, OrderEventEnum::Deliver, $supplier);

    expect($order->statusHistory()->orderBy('id')->get(['order_status', 'payment_status'])->toArray())->toBe([
        ['order_status' => Status::Draft->value, 'payment_status' => Payment::PENDING->value],
        ['order_status' => Status::Shipped->value, 'payment_status' => Payment::PENDING->value],
        ['order_status' => Status::Delivered->value, 'payment_status' => Payment::PAID->value],
    ]);
});

it('renders the transition table as a Mermaid state diagram', function () {
    $diagram = app(OrderStateMachine::class)->toMermaid();

    expect($diagram)->toStartWith('stateDiagram-v2')
        ->toContain('Draft --> Shipped: ship (cod)')
        ->toContain('Shipped --> Delivered: deliver');
});
