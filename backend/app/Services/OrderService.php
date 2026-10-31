<?php

namespace App\Services;

use App\Dtos\CheckoutDTO;
use App\Dtos\CreateOrderDTO;
use App\Dtos\OrderSearchRequestDTO;
use App\Dtos\SaveAddressDto;
use App\Enums\OrderEventEnum;
use App\Enums\OrderPaymentStatusEnum;
use App\Exceptions\CartException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\OrderCountException;
use App\Exceptions\OrderException;
use App\Exceptions\PaymentException;
use App\Exports\OrdersExport;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Payments\PaymentHandlerFactory;
use App\Repositories\AddressRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\ProductRepository;
use App\StateMachines\OrderStateMachine;
use App\ValueObjects\Money;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception;
use Stripe\Checkout\Session;
use Stripe\Event;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

#[Singleton]
class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly AddressRepository $addressRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly CartService $cartService,
        private readonly StripeService $stripeService,
        private readonly PaymentHandlerFactory $paymentHandlerFactory,
        private readonly NotificationHandlerService $notificationService,
        private readonly ProductRepository $productRepository,
        private readonly SavedAddressService $savedAddressService,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function getOrderById(int $orderId, User $user): Order
    {
        $order = $this->orderRepository->getOrderById($orderId);
        abort_unless($this->canViewOrder($order, $user), 403);

        return $order;
    }

    public function canViewOrder(Order $order, User $user): bool
    {
        return $user->isAdmin()
            || $order->user_id === $user->id
            || ($user->isSupplier() && $this->supplierOwnsOrder($order, $user));
    }

    /**
     * Whether the Supplier owns the Order and the transition table allows them at
     * least one event on it from its current state (e.g. not once it's terminal).
     */
    public function canSupplierActOn(Order $order, User $user): bool
    {
        return $user->isSupplier()
            && $this->supplierOwnsOrder($order, $user)
            && $this->stateMachine->availableEvents($order, $user) !== [];
    }

    public function getOrderForSupplierAction(int $orderId, User $user): Order
    {
        $order = $this->orderRepository->getOrderById($orderId);
        abort_unless($this->canSupplierActOn($order, $user), 403);

        return $order;
    }

    /**
     * @throws OrderException
     */
    public function applySupplierEvent(Order $order, User $user, OrderEventEnum $event): Order
    {
        return $this->stateMachine->apply($order, $event, $user);
    }

    /**
     * @param  array<int, array{product_id?: int|string|null}>  $items
     *
     * @throws OrderException
     */
    public function assertSingleSupplierForItems(array $items): void
    {
        $productIds = $this->extractProductIds($items);

        if (empty($productIds)) {
            return;
        }

        $supplierIds = collect($this->productRepository->getDistinctSupplierIds($productIds))->filter()->unique();

        if ($supplierIds->count() > 1) {
            throw OrderException::supplierMismatch();
        }
    }

    /**
     * Resolves the single owning Supplier for a set of Order items, falling back to
     * $fallbackUserId when the items list is empty (assumed already validated single-supplier
     * via assertSingleSupplierForItems()).
     *
     * @param  array<int, array{product_id?: int|string|null}>  $items
     */
    public function resolveSupplierIdForItems(array $items, int $fallbackUserId): int
    {
        $productIds = $this->extractProductIds($items);

        if (empty($productIds)) {
            return $fallbackUserId;
        }

        $supplierIds = collect($this->productRepository->getDistinctSupplierIds($productIds))->filter()->unique();

        return $supplierIds->first() ?? $fallbackUserId;
    }

    /**
     * @param  array<int, array{product_id?: int|string|null}>  $items
     * @return array<int, int|string>
     */
    private function extractProductIds(array $items): array
    {
        return collect($items)->pluck('product_id')->filter()->unique()->values()->all();
    }

    private function supplierOwnsOrder(Order $order, User $user): bool
    {
        return $order->supplier_id === $user->id;
    }

    public function getUsersLatestOrder(int $userId): Order
    {
        return $this->orderRepository->getLatestOrder($userId);
    }

    public function getUsersOrders(OrderSearchRequestDTO $dto): LengthAwarePaginator|array
    {
        return $this->orderRepository->getUsersOrders($dto);
    }

    public function getSupplierOrders(OrderSearchRequestDTO $dto): LengthAwarePaginator|array
    {
        return $this->orderRepository->getUsersOrders($dto);
    }

    public function countOrders(OrderSearchRequestDTO $dto): int
    {
        return $this->orderRepository->countOrders($dto);
    }

    /**
     * @throws OrderCountException
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportOrders(OrderSearchRequestDTO $dto): BinaryFileResponse
    {
        $count = $this->orderRepository->countOrders($dto);
        if ($count > 10000) {
            throw OrderCountException::limitExceeded($count);
        }

        return Excel::download(new OrdersExport($this->orderRepository, $dto), 'orders.xlsx');
    }

    /**
     * @throws OrderException
     * @throws EmptyCartException|PaymentException|Throwable
     */
    public function checkout(CheckoutDTO $dto): string
    {
        $paymentMethod = $dto->getPaymentMethod();
        try {
            DB::beginTransaction();
            // Two checkouts of the same cart at once (two tabs, a client retry)
            // would otherwise both read the full cart and create two Orders. The
            // second now waits here and then finds the cart already emptied.
            $this->cartService->lockCart();
            $cart = $this->cartService->getCart();
            Log::debug('OrderService checkout cartItems count: ', [$cart->cartItems->count()]);
            $this->handleEmptyCart($cart->cartItems);
            $line_items = $this->createLineItems($cart->cartItems);
            $order_items = $this->createOrderItems($cart->cartItems);
            $paymentMethods = $this->paymentMethodRepository->findAll()->pluck('id', 'resource_key');
            $paymentMethodId = $paymentMethods->get($paymentMethod);
            $supplierId = $this->cartService->getCartSupplierId($cart);
            Log::debug('OrderService creating order');
            $order = $this->createNewOrder($cart->total_price, $paymentMethodId, $order_items, $supplierId);
            Log::debug('OrderService created order ', [$order->id]);
            $redirect_url = $this->paymentHandlerFactory->make($paymentMethod)->process($order, $line_items);
            Log::debug('OrderService creating address');
            $this->addressRepository->create($order->id, $dto);
            Log::debug('OrderService created address');
            if ($dto->shouldSaveAddress()) {
                $this->savedAddressService->saveFromCheckout(auth()->user()->id, SaveAddressDto::fromCheckout($dto));
            }
            Log::debug('OrderService clearing cart');
            $this->cartService->clearCart();
            DB::commit();
            $order = $this->getUsersLatestOrder(auth()->user()->id);
            $this->notificationService->orderCreated($order);

            return $redirect_url;
        } catch (CartException|EmptyCartException|PaymentException $exception) {
            DB::rollBack();
            throw $exception;
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw OrderException::checkout();
        }
    }

    /**
     * @param  Collection<int, CartItem>  $cartItems
     *
     * @throws EmptyCartException
     */
    protected function handleEmptyCart(Collection $cartItems): void
    {
        if ($cartItems->isEmpty()) {
            throw EmptyCartException::emptyCart();
        }
    }

    /**
     * @param  Collection<int, CartItem>  $cartItems
     */
    protected function createLineItems(Collection $cartItems): array
    {
        return $cartItems->map(fn (CartItem $cartItem) => [
            'price_data' => [
                'currency' => config('app.currency'),
                'unit_amount' => $cartItem->unit_price->cents(),
                'product_data' => ['name' => $cartItem->product->name],
            ],
            'quantity' => $cartItem->quantity,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, CartItem>  $cartItems
     */
    protected function createOrderItems(Collection $cartItems): array
    {
        return $cartItems->map(fn (CartItem $cartItem) => OrderItem::fromCartItem($cartItem)->attributesToArray())->values()->all();
    }

    /**
     * @throws OrderException
     */
    protected function createNewOrder(Money $totalPrice, int $paymentMethodId, array $orderItems, int $supplierId): Order
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $createOrderDto = new CreateOrderDTO($user->id, $supplierId, $totalPrice, $paymentMethodId, $orderItems, $user->name);

            return $this->orderRepository->createOrder($createOrderDto);
        } catch (Throwable $e) {
            throw OrderException::createOrder();
        }
    }

    /**
     * The Buyer's own Order, or a 404. Used where a Buyer names an Order in the
     * URL (Stripe redirects), so another Buyer's id doesn't reveal it exists.
     */
    public function getBuyersOrder(int $orderId, User $buyer): Order
    {
        $order = $this->orderRepository->getUsersOrderById($orderId, $buyer->id);
        abort_if($order === null, 404);

        return $order;
    }

    /**
     * The success redirect: finalizes the Order behind $sessionId if the webhook
     * hasn't yet, so a Buyer who arrives first sees Paid straight away (ADR-0004).
     *
     * @throws OrderException|PaymentException
     */
    public function confirmStripeRedirect(string $sessionId, User $buyer): Order
    {
        $order = $this->orderRepository->getOrderByStripeSessionId($sessionId);
        abort_if($order === null || $order->user_id !== $buyer->id, 404);

        if ($order->payment_status !== OrderPaymentStatusEnum::PENDING->value) {
            return $order;
        }

        return $this->completeStripePayment($order, $this->stripeService->retrieveSession($sessionId));
    }

    /**
     * The Stripe session's URL while the Buyer can still pay the Order through it,
     * otherwise null. Lets the cancel page offer a retry on the same session.
     */
    public function getStripeRetryUrl(Order $order): ?string
    {
        $sessionId = $order->stripeOrderDetail?->session_id;

        if ($sessionId === null || $order->payment_status !== OrderPaymentStatusEnum::PENDING->value) {
            return null;
        }

        try {
            $session = $this->stripeService->retrieveSession($sessionId);
        } catch (PaymentException) {
            return null;
        }

        return $session->status === Session::STATUS_OPEN ? $session->url : null;
    }

    /**
     * Applies a verified Stripe webhook event to the Order behind its Checkout Session.
     * Events for unknown sessions, and event types we don't handle, are ignored.
     *
     * @throws OrderException
     */
    public function handleStripeEvent(Event $event): void
    {
        $session = $event->data->object;

        if (! $session instanceof Session) {
            return;
        }

        $order = $this->orderRepository->getOrderByStripeSessionId($session->id);

        if ($order === null) {
            return;
        }

        match ($event->type) {
            Event::CHECKOUT_SESSION_COMPLETED,
            Event::CHECKOUT_SESSION_ASYNC_PAYMENT_SUCCEEDED => $this->completeStripePayment($order, $session),
            Event::CHECKOUT_SESSION_ASYNC_PAYMENT_FAILED => $this->failStripePayment($order),
            Event::CHECKOUT_SESSION_EXPIRED => $this->expireStripePayment($order),
            default => null,
        };
    }

    /**
     * Shared by the success redirect and the webhook. Marks the Order Paid if the
     * session is paid. An unpaid completed session is an async payment still in
     * progress: the Order stays Pending until async_payment_succeeded/failed arrives.
     *
     * @throws OrderException
     */
    public function completeStripePayment(Order $order, Session $session): Order
    {
        if ($session->payment_status !== Session::PAYMENT_STATUS_PAID) {
            return $order;
        }

        // OrderObserver sends the payment-confirmed and ready-for-supplier Notifications.
        return $this->applyStripeEvent($order, OrderEventEnum::Pay);
    }

    /**
     * @throws OrderException
     */
    public function failStripePayment(Order $order): Order
    {
        return $this->applyStripeEvent(
            $order,
            OrderEventEnum::FailPayment,
            fn (Order $order) => $this->notificationService->orderPaymentFailed($order),
        );
    }

    /**
     * The Buyer never paid and the Checkout Session expired. Cancelling the Order
     * sends the Buyer the order-cancelled Notification through OrderObserver.
     *
     * @throws OrderException
     */
    public function expireStripePayment(Order $order): Order
    {
        return $this->applyStripeEvent($order, OrderEventEnum::Expire);
    }

    /**
     * The webhook and the success redirect routinely arrive within milliseconds of
     * each other, and Stripe retries. The state machine locks the row before
     * checking the table, so only one of them applies the change and its
     * Notifications are sent exactly once; the others are no-ops.
     *
     * @param  (callable(Order): void)|null  $afterTransition  runs after the commit, only if the event applied
     *
     * @throws OrderException
     */
    private function applyStripeEvent(Order $order, OrderEventEnum $event, ?callable $afterTransition = null): Order
    {
        try {
            $transitioned = $this->stateMachine->applyIfAllowed($order, $event, null);
        } catch (Throwable $exception) {
            Log::error($exception);
            throw OrderException::stripeProcessing();
        }

        if ($transitioned === null) {
            return $order->fresh();
        }

        if ($afterTransition !== null) {
            $afterTransition($transitioned);
        }

        return $transitioned;
    }
}
