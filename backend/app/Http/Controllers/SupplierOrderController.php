<?php

namespace App\Http\Controllers;

use App\Dtos\OrderSearchRequestDTO;
use App\Http\Requests\OrderSearchRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
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
}
