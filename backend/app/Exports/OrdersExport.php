<?php

namespace App\Exports;

use App\Dtos\OrderSearchRequestDTO;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

readonly class OrdersExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private OrderRepository       $orderRepository,
        private OrderSearchRequestDTO $dto
    ){}

    /**
    * @return Collection
    */
    public function collection(): Collection
    {
        return $this->orderRepository->getUsersOrdersForExport($this->dto)->get();
    }

    public function headings(): array
    {
        return [
            __('my-orders.columns.order'),
            __('my-orders.columns.date'),
            __('my-orders.columns.order_status'),
            __('my-orders.columns.payment_status'),
            __('my-orders.columns.amount'),
        ];
    }

    /**
     * @param Order $row
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->created_at->format('d M, Y'),
            OrderStatusEnum::labels()[$row->order_status] ?? $row->order_status,
            OrderPaymentStatusEnum::labels()[$row->payment_status] ?? $row->payment_status,
            Number::currency($row->grand_total ?? 0, 'eur'),
        ];
    }
}
