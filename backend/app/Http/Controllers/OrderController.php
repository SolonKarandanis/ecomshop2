<?php

namespace App\Http\Controllers;

use App\Data\OrderData;
use App\Dtos\OrderSearchRequestDTO;
use App\Http\Requests\OrderSearchRequest;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(OrderSearchRequest $request): PaginatedDataCollection|array
    {
        $dto = OrderSearchRequestDTO::fromRequest($request);

        return OrderData::collect($this->orderService->getUsersOrders($dto), PaginatedDataCollection::class);
    }

    public function show(int $order, Request $request): OrderData
    {
        return OrderData::from($this->orderService->getOrderById($order, $request->user()))->wrap('data');
    }

    public function export(OrderSearchRequest $request): BinaryFileResponse
    {
        $dto = OrderSearchRequestDTO::fromRequest($request);

        return $this->orderService->exportOrders($dto);
    }
}
