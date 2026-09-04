<?php

namespace App\Http\Controllers;

use App\Dtos\CheckoutDTO;
use App\Exceptions\EmptyCartException;
use App\Exceptions\OrderException;
use App\Exceptions\PaymentException;
use App\Http\Requests\CheckoutRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * @throws \Throwable
     * @throws OrderException
     * @throws PaymentException
     * @throws EmptyCartException
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        Gate::authorize('buyer-action');

        $dto = CheckoutDTO::fromRequest($request);
        $redirectUrl = $this->orderService->checkout($dto);

        return response()->json(['redirect_url' => $redirectUrl]);
    }
}
