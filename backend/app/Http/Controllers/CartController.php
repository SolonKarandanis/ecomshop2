<?php

namespace App\Http\Controllers;

use App\Data\CartData;
use App\Dtos\AddToCartDto;
use App\Dtos\UpdateCartItemsDTO;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemQuantityRequest;
use App\Services\CartService;
use Illuminate\Support\Facades\Gate;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function index(): CartData
    {
        return CartData::from($this->cartService->getCart())->wrap('data');
    }

    public function store(AddCartItemRequest $request): CartData
    {
        Gate::authorize('buyer-action');

        $dto = AddToCartDto::withAttributes(
            $request->integer('product_id'),
            $request->integer('quantity', 1),
            0,
            $request->input('attributes', []),
        );

        $this->cartService->addItemsToCart([$dto]);

        return CartData::from($this->cartService->getCart())->wrap('data');
    }

    public function update(string $cartItemId, UpdateCartItemQuantityRequest $request): CartData
    {
        $cart = $this->cartService->getCart();
        $cartItem = $this->findCartItem($cart, $cartItemId);
        abort_if($cartItem === null, 404);

        $dto = new UpdateCartItemsDTO(
            $cartItemId,
            $cartItem->product_id,
            $request->integer('quantity'),
            $cartItem->attributes ?? [],
        );

        $this->cartService->updateItemsQuantity($cart, [$dto]);

        return CartData::from($this->cartService->getCart())->wrap('data');
    }

    public function destroy(string $cartItemId): CartData
    {
        $this->cartService->removeItemsFromCart([$cartItemId]);

        return CartData::from($this->cartService->getCart())->wrap('data');
    }

    private function findCartItem($cart, string $cartItemId)
    {
        return $cart->cartItems->first(
            fn ($item) => (string) ($item->id ?? $item->id_from_cookie) === $cartItemId
        );
    }
}
