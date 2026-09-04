<?php

namespace App\Dtos;

use App\Http\Requests\OrderSearchRequest;

class OrderSearchRequestDTO
{
    private int $userId;

    private ?int $supplierId = null;

    private ?string $orderStatus = null;

    private ?string $paymentStatus = null;

    private ?string $fromDate = null;

    private ?string $toDate = null;

    private ?float $minPrice = null;

    private ?float $maxPrice = null;

    private int $perPage = 5;

    private string $sortColumn = 'created_at';

    private string $sortDirection = 'desc';

    public function __construct()
    {
        $this->userId = auth()->user()->id;
    }

    public static function fromRequest(OrderSearchRequest $request): self
    {
        $instance = new self;
        $instance->withOrderStatus($request->input('orderStatus'));
        $instance->withPaymentStatus($request->input('paymentStatus'));
        $instance->withFromDate($request->input('fromDate'));
        $instance->withToDate($request->input('toDate'));
        $instance->withMinPrice($request->filled('minPrice') ? (float) $request->input('minPrice') : null);
        $instance->withMaxPrice($request->filled('maxPrice') ? (float) $request->input('maxPrice') : null);
        $instance->withSortColumn($request->input('sortColumn', $instance->getSortColumn()));
        $instance->withSortDirection($request->input('sortDirection', $instance->getSortDirection()));

        return $instance;
    }

    public static function fromArray(array $data): self
    {
        $instance = new self;
        $instance->withOrderStatus($data['orderStatus'] ?? null);
        $instance->withPaymentStatus($data['paymentStatus'] ?? null);
        $instance->withMinPrice($data['minPrice'] ?? null);
        $instance->withMaxPrice($data['maxPrice'] ?? null);
        $instance->withFromDate($data['fromDate'] ?? null);
        $instance->withToDate($data['toDate'] ?? null);

        return $instance;
    }

    public function withOrderStatus(?string $orderStatus): self
    {
        $this->orderStatus = $orderStatus;

        return $this;
    }

    public function withPaymentStatus(?string $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    public function withFromDate(?string $fromDate): self
    {
        $this->fromDate = $fromDate;

        return $this;
    }

    public function withToDate(?string $toDate): self
    {
        $this->toDate = $toDate;

        return $this;
    }

    public function withMinPrice(?float $minPrice): self
    {
        $this->minPrice = $minPrice;

        return $this;
    }

    public function withMaxPrice(?float $maxPrice): self
    {
        $this->maxPrice = $maxPrice;

        return $this;
    }

    public function withSupplierId(?int $supplierId): self
    {
        $this->supplierId = $supplierId;

        return $this;
    }

    public function withPerPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function withSortColumn(string $sortColumn): self
    {
        $this->sortColumn = $sortColumn;

        return $this;
    }

    public function withSortDirection(string $sortDirection): self
    {
        $this->sortDirection = $sortDirection;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSupplierId(): ?int
    {
        return $this->supplierId;
    }

    public function getOrderStatus(): ?string
    {
        return $this->orderStatus;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function getFromDate(): ?string
    {
        return $this->fromDate;
    }

    public function getToDate(): ?string
    {
        return $this->toDate;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getMinPrice(): ?float
    {
        return $this->minPrice;
    }

    public function getMaxPrice(): ?float
    {
        return $this->maxPrice;
    }

    public function getSortColumn(): string
    {
        return $this->sortColumn;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }
}
