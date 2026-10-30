<?php

namespace App\Http\Controllers;

use App\Data\OrderData;
use App\Dtos\OrderSearchRequestDTO;
use App\Enums\OrderEventEnum;
use App\Http\Requests\OrderSearchRequest;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class SupplierOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(OrderSearchRequest $request): PaginatedDataCollection|array
    {
        abort_unless(Gate::allows('supplier-action'), 403);

        $dto = OrderSearchRequestDTO::fromRequest($request)->withSupplierId($request->user()->id);

        return OrderData::collect($this->orderService->getSupplierOrders($dto), PaginatedDataCollection::class);
    }

    public function ship(int $order, Request $request): OrderData
    {
        return $this->transition($order, $request, OrderEventEnum::Ship);
    }

    public function deliver(int $order, Request $request): OrderData
    {
        return $this->transition($order, $request, OrderEventEnum::Deliver);
    }

    public function cancel(int $order, Request $request): OrderData
    {
        return $this->transition($order, $request, OrderEventEnum::Cancel);
    }

    private function transition(int $orderId, Request $request, OrderEventEnum $event): OrderData
    {
        abort_unless(Gate::allows('supplier-action'), 403);

        $order = $this->orderService->getOrderForSupplierAction($orderId, $request->user());
        $order = $this->orderService->applySupplierEvent($order, $request->user(), $event);

        return OrderData::from($order->load('statusHistory'))->wrap('data');
    }
}
