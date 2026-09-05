<?php

namespace App\Http\Controllers;

use App\Dtos\OrderSearchRequestDTO;
use App\Enums\OrderStatusEnum;
use App\Http\Requests\OrderSearchRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SupplierOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(OrderSearchRequest $request): AnonymousResourceCollection
    {
        abort_unless(Gate::allows('supplier-action'), 403);

        $dto = OrderSearchRequestDTO::fromRequest($request)->withSupplierId($request->user()->id);

        return OrderResource::collection($this->orderService->getSupplierOrders($dto));
    }

    public function ship(int $order, Request $request): OrderResource
    {
        return $this->transition($order, $request, OrderStatusEnum::Shipped);
    }

    public function deliver(int $order, Request $request): OrderResource
    {
        return $this->transition($order, $request, OrderStatusEnum::Delivered);
    }

    public function cancel(int $order, Request $request): OrderResource
    {
        return $this->transition($order, $request, OrderStatusEnum::Cancelled);
    }

    private function transition(int $orderId, Request $request, OrderStatusEnum $toStatus): OrderResource
    {
        abort_unless(Gate::allows('supplier-action'), 403);

        $order = $this->orderService->getOrderForSupplierAction($orderId, $request->user());
        $order = $this->orderService->transitionOrderStatusBySupplier($order, $request->user(), $toStatus);

        return new OrderResource($order);
    }
}
