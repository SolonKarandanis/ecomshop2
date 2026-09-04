<?php

namespace App\Http\Controllers;

use App\Dtos\OrderSearchRequestDTO;
use App\Http\Requests\OrderSearchRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(OrderSearchRequest $request): AnonymousResourceCollection
    {
        $dto = OrderSearchRequestDTO::fromRequest($request);

        return OrderResource::collection($this->orderService->getUsersOrders($dto));
    }

    public function show(int $order, Request $request): OrderResource
    {
        return new OrderResource($this->orderService->getOrderById($order, $request->user()));
    }
}
