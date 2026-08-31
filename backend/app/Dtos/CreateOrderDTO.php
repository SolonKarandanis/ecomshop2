<?php

namespace App\Dtos;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\ShippingMethodEnum;

class CreateOrderDTO
{
    private int $userId;
    private int $supplierId;
    private float $totalPrice;
    private int $paymentMethodId;
    private string $paymentStatus;
    private string $orderStatus;
    private string $currency;
    private float $shippingAmount;
    private string $shippingMethod;
    private string $notes;
    private array $orderItems;

    public function __construct(
        int $userId, int $supplierId, float $totalPrice, int $paymentMethodId, array $orderItems, string $userName){
        $this->userId = $userId;
        $this->supplierId = $supplierId;
        $this->totalPrice = $totalPrice;
        $this->paymentMethodId = $paymentMethodId;
        $this->paymentStatus=OrderPaymentStatusEnum::PENDING->value;
        $this->orderStatus=OrderStatusEnum::Draft->value;
        $this->currency = config('app.currency');
        $this->shippingAmount = 0;
        $this->shippingMethod = ShippingMethodEnum::NONE->value;
        $this->notes = 'Order placed'.$userName;
        $this->orderItems = $orderItems;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSupplierId(): int
    {
        return $this->supplierId;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function getPaymentMethodId(): int
    {
        return $this->paymentMethodId;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function getOrderStatus(): string
    {
        return $this->orderStatus;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getShippingAmount(): float
    {
        return $this->shippingAmount;
    }

    public function getShippingMethod(): string
    {
        return $this->shippingMethod;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function getOrderItems(): array
    {
        return $this->orderItems;
    }

    public function setShippingAmount(float $shippingAmount): void
    {
        $this->shippingAmount = $shippingAmount;
    }

    public function setShippingMethod(string $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function setNotes(string $notes): void
    {
        $this->notes = $notes;
    }
}
