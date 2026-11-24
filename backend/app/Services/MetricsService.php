<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Str;

/**
 * Reads the business figures served to Prometheus, already shaped as gauge
 * values: a list of [value, [label]] pairs for the labelled gauges.
 */
#[Singleton]
class MetricsService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly ProductRepository $productRepository,
    ) {}

    /**
     * @return list<array{int, list<string>}>
     */
    public function ordersByStatus(): array
    {
        return $this->labelledByStatus($this->orderRepository->countByOrderStatus());
    }

    /**
     * @return list<array{int, list<string>}>
     */
    public function ordersByPaymentStatus(): array
    {
        return $this->labelledByStatus($this->orderRepository->countByPaymentStatus());
    }

    /**
     * @return list<array{float, list<string>}>
     */
    public function paidRevenueByCurrency(): array
    {
        return collect($this->orderRepository->sumPaidGrandTotalsByCurrency())
            ->map(fn (float $sum, string $currency): array => [$sum, [$currency === '' ? 'none' : Str::upper($currency)]])
            ->values()
            ->all();
    }

    /**
     * @return list<array{int, list<string>}>
     */
    public function usersByStatus(): array
    {
        return $this->labelledByStatus($this->userRepository->countByStatus());
    }

    public function activeProducts(): int
    {
        return $this->productRepository->countActive();
    }

    public function outOfStockProducts(): int
    {
        return $this->productRepository->countActiveOutOfStock();
    }

    /**
     * Turns counts keyed by a stored status such as 'order.status.paid' into
     * gauge values labelled 'paid'; an empty status is labelled 'none'.
     *
     * @param  array<string, int>  $countsByStatus
     * @return list<array{int, list<string>}>
     */
    private function labelledByStatus(array $countsByStatus): array
    {
        return collect($countsByStatus)
            ->map(fn (int $count, string $status): array => [$count, [$status === '' ? 'none' : Str::afterLast($status, '.')]])
            ->values()
            ->all();
    }
}
