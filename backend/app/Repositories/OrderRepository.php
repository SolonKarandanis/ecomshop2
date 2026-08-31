<?php

namespace App\Repositories;

use App\Dtos\CreateOrderDTO;
use App\Dtos\OrderSearchRequestDTO;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderRepository
{
    public function modelQuery(): Builder|Order
    {
        return Order::query();
    }

    public function itemModelQuery(): Builder|OrderItem
    {
        return OrderItem::query();
    }

    /**
     * @throws Throwable
     */
    public function createOrder(CreateOrderDTO $createOrderDTO): Order
    {
        return DB::transaction(function () use ($createOrderDTO) {
            $order = $this->modelQuery()->create([
                'user_id' => $createOrderDTO->getUserId(),
                'supplier_id' => $createOrderDTO->getSupplierId(),
                'grand_total' => $createOrderDTO->getTotalPrice(),
                'payment_method_id' => $createOrderDTO->getPaymentMethodId(),
                'payment_status' => $createOrderDTO->getPaymentStatus(),
                'order_status' => $createOrderDTO->getOrderStatus(),
                'currency' => $createOrderDTO->getCurrency(),
                'shipping_method' => $createOrderDTO->getShippingMethod(),
                'shipping_amount' => $createOrderDTO->getShippingAmount(),
                'notes' => $createOrderDTO->getNotes(),
            ]);

            $order->items()->createMany($createOrderDTO->getOrderItems());

            return $order;
        });
    }

    public function getOrderById(int $orderId): Order
    {
        return $this->modelQuery()
            ->with([
                'address',
                'items',
                'items.product',
                'items.product.productAttributeValues.attribute',
                'items.product.productAttributeValues.media',
            ])
            ->where('id', $orderId)
            ->firstOrFail();
    }

    public function getLatestOrder(int $userId): Order
    {
        return $this->modelQuery()
            ->with([
                'address',
                'paymentMethod',
                'items',
                'items.product',
                'items.product.productAttributeValues.attribute',
                'items.product.productAttributeValues.media',
            ])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getUsersOrders(OrderSearchRequestDTO $dto): LengthAwarePaginator|array
    {
        $orderQuery = $this->applySearchFilters($dto);

        return $orderQuery
            ->orderBy($dto->getSortColumn(), $dto->getSortDirection())
            ->paginate($dto->getPerPage());
    }

    public function getUsersOrdersForExport(OrderSearchRequestDTO $dto): Builder
    {
        return $this->applySearchFilters($dto)->orderBy('created_at', 'desc');
    }

    public function countOrders(OrderSearchRequestDTO $dto): int
    {
        return $this->applySearchFilters($dto)->count();
    }

    private function applySearchFilters(OrderSearchRequestDTO $dto): Builder
    {
        $orderQuery = $this->modelQuery();

        if ($dto->getSupplierId() !== null) {
            $orderQuery->where('supplier_id', $dto->getSupplierId())
                ->where('order_status', '!=', OrderStatusEnum::Draft->value);
        } else {
            $orderQuery->where('user_id', $dto->getUserId());
        }

        $orderQuery->when(! empty($dto->getOrderStatus()), function ($query) use ($dto) {
            $query->where('order_status', $dto->getOrderStatus());
        });

        $orderQuery->when(! empty($dto->getPaymentStatus()), function ($query) use ($dto) {
            $query->where('payment_status', $dto->getPaymentStatus());
        });

        $orderQuery->when(! empty($dto->getFromDate()), function ($query) use ($dto) {
            $query->whereDate('created_at', '>=', $dto->getFromDate());
        });

        $orderQuery->when(! empty($dto->getToDate()), function ($query) use ($dto) {
            $query->whereDate('created_at', '<=', $dto->getToDate());
        });

        $orderQuery->when(! empty($dto->getMinPrice()), function ($query) use ($dto) {
            $query->where('grand_total', '>=', $dto->getMinPrice());
        });

        $orderQuery->when(! empty($dto->getMaxPrice()), function ($query) use ($dto) {
            $query->where('grand_total', '<=', $dto->getMaxPrice());
        });

        return $orderQuery;
    }

    public function updateOrder(Order $order): bool
    {
        return $order->save();
    }

    public function getOrders(): Collection|Builder
    {
        return $this->modelQuery()->get();
    }

    public function hasDeliveredOrderForProduct(int $userId, int $productId): bool
    {
        return $this->modelQuery()
            ->where('user_id', $userId)
            ->where('order_status', OrderStatusEnum::Delivered->value)
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }
}
