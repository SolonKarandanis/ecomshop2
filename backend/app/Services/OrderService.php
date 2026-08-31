<?php

namespace App\Services;

use App\Dtos\CheckoutDTO;
use App\Dtos\CreateOrderDTO;
use App\Dtos\OrderSearchRequestDTO;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\StripePaymentStatusEnum;
use App\Exceptions\EmptyCartException;
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
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception;
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

    public function canSupplierActOn(Order $order, User $user): bool
    {
        return $user->isSupplier()
            && $this->supplierOwnsOrder($order, $user)
            && in_array($order->order_status, [OrderStatusEnum::Paid->value, OrderStatusEnum::Shipped->value], true);
    }

    /**
     * @throws OrderException
     */
    public function transitionOrderStatusBySupplier(Order $order, User $user, OrderStatusEnum $toStatus): Order
    {
        if (! $this->canSupplierActOn($order, $user) || ! $this->isLegalSupplierTransition($order->order_status, $toStatus->value)) {
            throw OrderException::supplierActionNotAllowed();
        }

        $order->order_status = $toStatus->value;
        $this->orderRepository->updateOrder($order);

        return $order;
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

    private function isLegalSupplierTransition(string $from, string $to): bool
    {
        return match ($from) {
            OrderStatusEnum::Paid->value => in_array($to, [OrderStatusEnum::Shipped->value, OrderStatusEnum::Cancelled->value], true),
            OrderStatusEnum::Shipped->value => $to === OrderStatusEnum::Delivered->value,
            default => false,
        };
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
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportOrders(OrderSearchRequestDTO $dto): BinaryFileResponse
    {
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
            Log::debug('OrderService clearing cart');
            $this->cartService->clearCart();
            DB::commit();
            $order = $this->getUsersLatestOrder(auth()->user()->id);
            $this->notificationService->orderCreated($order);

            return $redirect_url;
        } catch (EmptyCartException|PaymentException $exception) {
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
                'unit_amount' => $cartItem->unit_price * 100, // stripe wants cents
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
        return $cartItems->map(fn (CartItem $cartItem) => (new OrderItem($cartItem))->attributesToArray())->values()->all();
    }

    /**
     * @throws OrderException
     */
    protected function createNewOrder(float $totalPrice, int $paymentMethodId, array $orderItems, int $supplierId): Order
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
     * @throws OrderException|Throwable
     */
    public function successOrFailStripeOrder(string $sessionId, Order $latestOrder): Order
    {
        try {
            DB::beginTransaction();
            $sessionInfo = $this->stripeService->retrieveSession($sessionId);
            $isPaid = $sessionInfo->payment_status == StripePaymentStatusEnum::PAID->value;
            if ($isPaid) {
                $latestOrder->payment_status = OrderPaymentStatusEnum::PAID->value;
                $latestOrder->order_status = OrderStatusEnum::Paid->value;
            } else {
                $latestOrder->payment_status = OrderPaymentStatusEnum::FAILED->value;
            }
            $this->orderRepository->updateOrder($latestOrder);
            DB::commit();
            if ($isPaid) {
                $this->notificationService->orderPaymentConfirmed($latestOrder);
                if (config('features.suppliers_enabled')) {
                    $this->notificationService->orderReadyForSupplier($latestOrder);
                }
            } else {
                $this->notificationService->orderPaymentFailed($latestOrder);
            }

            return $latestOrder;
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw OrderException::stripeProcessing();
        }
    }
}
