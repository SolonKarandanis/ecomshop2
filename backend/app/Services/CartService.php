<?php

namespace App\Services;

use App\Dtos\AddToCartDto;
use App\Dtos\UpdateCartItemsDTO;
use App\Exceptions\CartException;
use App\Exceptions\ProductNotFoundException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Repositories\CartRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Singleton]
class CartService
{
    protected const COOKIE_CART_NAME = 'cart';

    protected const COOKIE_CART_ITEMS_NAME = 'cartItems';

    protected const COOKIE_LIFETIME = 60 * 24 * 365; // 1 year

    private ?Cart $cachedCart = null;

    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly ProductService $productService
    ) {}

    public function getCart(): Cart
    {
        Log::debug('Cached cart', [! is_null($this->cachedCart)]);
        if ($this->cachedCart) {
            return $this->cachedCart;
        }
        if (Auth::check()) {
            Log::debug('Getting cart from database');
            $cart = $this->getCartFromDatabase();
        } else {
            Log::debug('Getting cart from cookies');
            $cart = $this->getCartFromCookies();
        }
        $this->cachedCart = $cart;

        return $this->cachedCart;
    }

    /**
     * The Cart's single owning Supplier, resolved from the same source
     * assertSingleSupplier() validates against (ADR-0008). Assumes the Cart
     * is non-empty and single-supplier, both already guaranteed by the time
     * checkout is reached.
     */
    public function getCartSupplierId(Cart $cart): int
    {
        return collect($this->cartRepository->getDistinctSupplierIds($cart->id))->filter()->first();
    }

    protected function getCartFromCookies(): Cart
    {
        $cartData = json_decode($this->getFromCookies(self::COOKIE_CART_NAME), true);
        $cart = new Cart($cartData);

        $cookieValue = $this->getFromCookies(self::COOKIE_CART_ITEMS_NAME);
        Log::debug('Raw cartItems cookie value from request(): ', [$cookieValue]);
        $cartItemsData = json_decode($cookieValue, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::debug('JSON decode error: '.json_last_error_msg());
            $cartItemsData = [];
        }

        $cartItems = [];
        $productIds = [];
        foreach ($cartItemsData as $key => $itemData) {
            $productId = $itemData['product_id'];
            $productIds[] = $productId;

            $modelData = [
                'product_id' => $productId,
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['price'],
                'total_price' => $itemData['price'] * $itemData['quantity'],
                'attributes' => $itemData['attribute_ids'],
            ];
            $cartItem = new CartItem($modelData);
            // This is a bit of a hack to keep the cookie item id without modifying the model
            $cartItem->id_from_cookie = $itemData['id'] ?? null;
            $cartItems[] = $cartItem;
        }
        $products = $this->productService->findProductsForCart($productIds);
        foreach ($cartItems as $cartItem) {
            $product = $products->get($cartItem->product_id);
            if ($product) {
                $cartItem->setRelation('product', $product);
            }
        }
        $cart->setRelation('cartItems', collect($cartItems));
        $this->recalculateCartTotalPrice($cart);

        return $cart;
    }

    protected function getCartFromDatabase(): Cart
    {
        $userId = Auth::id();

        return $this->cartRepository->getCart($userId);
    }

    /**
     * @param  AddToCartDto[]  $addToCartRequests
     *
     * @throws CartException
     * @throws ProductNotFoundException
     */
    public function addItemsToCart(array $addToCartRequests): bool
    {
        if (Auth::check()) {
            return $this->saveCartToDatabase($addToCartRequests);
        } else {
            return $this->saveCartToCookies($addToCartRequests);
        }
    }

    /**
     * @param  AddToCartDto[]  $addToCartRequests
     *
     * @throws CartException
     * @throws ProductNotFoundException
     */
    public function saveCartToDatabase(array $addToCartRequests): bool
    {
        DB::beginTransaction();
        try {
            $cartId = $this->cartRepository->getCartId(Auth::id());
            Log::debug('Cart id ', [$cartId]);
            $productsToBeAdded = $this->fetchProductsToBeAdded($addToCartRequests);
            $this->assertSingleSupplier($this->cartRepository->getDistinctSupplierIds($cartId), $productsToBeAdded);
            $newCartItems = [];
            foreach ($addToCartRequests as $request) {
                $product = $productsToBeAdded->find($request->getProductId());
                $this->handleProductNotFound($request, $product);
                $this->setAttributesIfEmptyToRequest($request, $productsToBeAdded);
                $attributes = $request->getAttributes();
                ksort($attributes);
                $request->setAttributes($attributes);
                $existingItem = $this->cartRepository->findItemByProductIdAndAttributes(
                    $cartId,
                    $request->getProductId(),
                    $attributes
                );
                // Check if the item is already in $newCartItems to be added
                $alreadyInNewItems = $this->checkIfItemIsAlreadyInNewItems($newCartItems, $request, $attributes);
                if ($alreadyInNewItems) {
                    continue;
                }
                $price = $this->calculatePriceWithAttributes($product, $attributes);
                $request->setPrice($price);
                if ($existingItem) {
                    $request->setQuantity($existingItem->quantity + $request->getQuantity());
                    $totalPrice = $request->getQuantity() * $request->getPrice();
                    $this->cartRepository->updateItemQuantity($existingItem->id, $request->getQuantity(), $request->getPrice(), $totalPrice);
                } else {
                    $newCartItems[] = $request;
                }
            }
            if (! empty($newCartItems)) {
                $this->cartRepository->createCartItems($cartId, $newCartItems);
            }
            $this->recalculateCartTotalPrice();
            DB::commit();

            return true;
        } catch (ProductNotFoundException|CartException $e) {
            DB::rollBack();
            throw $e;
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw CartException::saveCart();
        }
    }

    public function checkIfItemIsAlreadyInNewItems(array $newCartItems, AddToCartDto $request, array $attributes): bool
    {
        $alreadyInNewItems = false;
        foreach ($newCartItems as $newItemDto) {
            if ($newItemDto->getProductId() === $request->getProductId()) {
                $newItemAttributes = $newItemDto->getAttributes();
                ksort($newItemAttributes);
                if ($newItemAttributes === $attributes) {
                    $newItemDto->setQuantity($newItemDto->getQuantity() + $request->getQuantity());
                    $alreadyInNewItems = true;
                    break;
                }
            }
        }

        return $alreadyInNewItems;
    }

    /**
     * @throws ProductNotFoundException
     */
    protected function handleProductNotFound(AddToCartDto $request, ?Product $product): void
    {
        if (! $product) {
            throw ProductNotFoundException::productNotFound($request->getProductId());
        }
    }

    /**
     * @param  Collection<int, Product>  $productsToBeAdded
     *
     * @throws CartException
     */
    protected function assertSingleSupplier(array $existingSupplierIds, Collection $productsToBeAdded): void
    {
        $newSupplierIds = $productsToBeAdded->pluck('supplier_id')->all();

        $supplierIds = collect([...$existingSupplierIds, ...$newSupplierIds])->filter()->unique();

        if ($supplierIds->count() > 1) {
            throw CartException::supplierMismatch();
        }
    }

    /**
     * @param  Collection<int, CartItem>  $cartItems
     */
    protected function findExistingCartItem(Collection $cartItems, int $productId, array $attributes): ?CartItem
    {
        return $cartItems->first(function (CartItem $item) use ($productId, $attributes) {
            $itemAttributes = $item->attributes ?? [];
            if (is_string($itemAttributes)) {
                $itemAttributes = json_decode($itemAttributes, true) ?? [];
            }
            ksort($itemAttributes);

            return (int) $item->product_id === (int) $productId && $itemAttributes === $attributes;
        });
    }

    /**
     * @param  AddToCartDto[]  $addToCartRequests
     *
     * @throws ProductNotFoundException
     * @throws CartException
     */
    public function saveCartToCookies(array $addToCartRequests): bool
    {
        $cart = $this->getCart();
        $productsToBeAdded = $this->fetchProductsToBeAdded($addToCartRequests);
        $existingSupplierIds = $cart->cartItems
            ->map(fn (CartItem $item) => $item->product?->supplier_id)
            ->all();
        $this->assertSingleSupplier($existingSupplierIds, $productsToBeAdded);
        foreach ($addToCartRequests as $request) {
            $product = $productsToBeAdded->find($request->getProductId());
            $this->handleProductNotFound($request, $product);
            $this->setAttributesIfEmptyToRequest($request, $productsToBeAdded);
            $attributes = $request->getAttributes();
            ksort($attributes);
            $existingItem = $this->findExistingCartItem($cart->cartItems, $request->getProductId(), $attributes);
            $price = $this->calculatePriceWithAttributes($product, $attributes);
            if ($existingItem) {
                $existingItem->quantity += $request->getQuantity();
            } else {
                $newItemData = [
                    'product_id' => $request->getProductId(),
                    'quantity' => $request->getQuantity(),
                    'unit_price' => $price,
                    'attributes' => $attributes,
                ];
                $newItem = new CartItem($newItemData);
                $newItem->id_from_cookie = (string) Str::uuid();
                $cart->cartItems->push($newItem);
            }
        }
        $cartItemsForCookie = [];
        foreach ($cart->cartItems as $item) {
            $itemAttributes = $item->attributes ?? [];
            ksort($itemAttributes);
            $cartItemsForCookie[] = [
                'id' => $item->id_from_cookie,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->unit_price,
                'attribute_ids' => $itemAttributes,
            ];
        }
        $this->putItemsToCookies($cartItemsForCookie);
        $this->recalculateCartTotalPrice();

        return true;
    }

    /**
     * @param  UpdateCartItemsDTO[]  $updateCartItemRequests
     *
     * @throws CartException
     */
    public function updateItemsQuantity(Cart $cart, array $updateCartItemRequests): bool
    {
        if (Auth::check()) {
            return $this->updateCartItemsInDatabase($cart, $updateCartItemRequests);
        } else {
            return $this->updateCartItemsInCookies($cart, $updateCartItemRequests);
        }
    }

    /**
     * @param  UpdateCartItemsDTO[]  $updateCartItemRequests
     *
     * @throws CartException
     */
    protected function updateCartItemsInDatabase(Cart $cart, array $updateCartItemRequests): bool
    {
        Log::debug('Attempting to update cart items in the database.');
        DB::beginTransaction();
        try {
            $cartItems = $cart->cartItems;
            $updates = [];
            $idsToUpdate = [];
            foreach ($updateCartItemRequests as $request) {
                $cartItemId = $request->getCartItemId();
                $quantity = $request->getQuantity();
                $attributes = $request->getAttributes();
                ksort($attributes);
                $existingCartItem = $this->findExistingCartItemForUpdate($cartItems, $cartItemId);
                if ($existingCartItem !== null) {
                    $totalPrice = $quantity * $existingCartItem->unit_price;
                    $updates[] = [
                        'id' => $existingCartItem->id,
                        'quantity' => $quantity,
                        'total_price' => $totalPrice,
                        'attributes' => $attributes,
                    ];
                    $idsToUpdate[] = $existingCartItem->id;

                    $existingCartItem->quantity = $quantity;
                    $existingCartItem->total_price = $totalPrice;
                    $existingCartItem->attributes = $attributes;
                }
            }
            if (empty($updates)) {
                DB::rollBack();

                return true;
            }
            $this->cartRepository->batchUpdateCartItems($updates, $idsToUpdate);
            $this->recalculateCartTotalPrice($cart);
            DB::commit();

            return true;
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw CartException::updateItems();
        }
    }

    /**
     * @param  UpdateCartItemsDTO[]  $updateCartItemRequests
     */
    protected function updateCartItemsInCookies(Cart $cart, array $updateCartItemRequests): bool
    {
        $cartItems = $cart->cartItems;
        $cartItemsForCookie = [];
        foreach ($cartItems as $item) {
            $attributeIds = $item->attributes ?? [];
            ksort($attributeIds);
            $key = $item->product_id.'_'.json_encode($attributeIds);
            $cartItemsForCookie[$key] = [
                'id' => $item->id_from_cookie,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->unit_price,
                'attribute_ids' => $attributeIds,
            ];
        }
        foreach ($updateCartItemRequests as $request) {
            $cartItemId = $request->getCartItemId();
            $quantity = $request->getQuantity();
            $attributes = $request->getAttributes();
            ksort($attributes);
            $key = $request->getProductId().'_'.json_encode($attributes);

            if (isset($cartItemsForCookie[$key]) && $cartItemsForCookie[$key]['id'] === $cartItemId) {
                Log::debug('Updating quantity in cookie for item: '.$cartItemId.' to quantity: '.$quantity);
                $cartItemsForCookie[$key]['quantity'] = $quantity;
            }
        }
        $this->putItemsToCookies($cartItemsForCookie);
        $updatedCartItems = [];
        foreach ($cartItemsForCookie as $itemData) {
            $modelData = [
                'product_id' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['price'],
                'total_price' => $itemData['price'] * $itemData['quantity'],
                'attributes' => $itemData['attribute_ids'],
            ];
            $cartItem = new CartItem($modelData);
            $cartItem->id_from_cookie = $itemData['id'] ?? null;
            $updatedCartItems[] = $cartItem;
        }
        // Eager load the product relationships for the updated cart items
        $productIds = collect($updatedCartItems)->pluck('product_id')->all();
        if (! empty($productIds)) {
            $products = $this->productService->findProductsByIds($productIds)->keyBy('id');
            foreach ($updatedCartItems as $cartItem) {
                if (isset($products[$cartItem->product_id])) {
                    $cartItem->setRelation('product', $products[$cartItem->product_id]);
                }
            }
        }
        $cart->setRelation('cartItems', collect($updatedCartItems));
        $this->recalculateCartTotalPrice($cart);

        return true;
    }

    /**
     * @throws CartException
     */
    public function removeItemsFromCart(array $cartItemIds): bool
    {
        if (Auth::check()) {
            return $this->deleteItemsFromDatabase($cartItemIds);
        } else {
            return $this->deleteItemsFromCookies($cartItemIds);
        }
    }

    /**
     * @throws CartException
     */
    protected function deleteItemsFromDatabase(array $cartItemIds): bool
    {
        DB::beginTransaction();
        try {
            $cartId = $this->cartRepository->getCartId(Auth::id());
            $this->cartRepository->deleteCartItems($cartId, $cartItemIds);
            $this->recalculateCartTotalPrice();
            DB::commit();

            return true;
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw CartException::deleteItems();
        }
    }

    protected function deleteItemsFromCookies(array $cartItemIds): bool
    {
        $cart = $this->getCart();

        $itemsToKeep = $cart->cartItems->reject(function ($item) use ($cartItemIds) {
            return in_array($item->id_from_cookie, $cartItemIds);
        });

        $cart->setRelation('cartItems', $itemsToKeep);

        $cartItemsForCookie = collect($itemsToKeep)
            ->flatMap(fn ($item) => $this->getCartItemsForCookies($item))
            ->all();
        $cartItemsForCookie = array_merge($cartItemsForCookie, $cartItemsForCookie);
        Log::debug('Remaining cart items after delete: ', [$cartItemsForCookie]);
        $this->putItemsToCookies($cartItemsForCookie);
        $this->recalculateCartTotalPrice($cart);

        return true;
    }

    /**
     * @throws CartException
     */
    public function clearCart(): bool
    {
        $result = false;
        if (Auth::check()) {
            $result = $this->clearCartFromDatabase();
            $this->cachedCart = null;
        } else {
            $result = $this->clearCartFromCookies();
            // Cookie::forget is queued and only takes effect when the response is sent,
            // so the cookies are still readable within this request. Seed the cache with
            // an empty cart so any subsequent getCart() call in the same request doesn't
            // re-read the stale cookies.
            $empty = new Cart(['total_price' => 0]);
            $empty->setRelation('cartItems', collect());
            $this->cachedCart = $empty;
        }

        return $result;
    }

    /**
     * @throws CartException
     */
    protected function clearCartFromDatabase(): bool
    {
        DB::beginTransaction();
        try {
            $cartId = $this->cartRepository->getCartId(Auth::id());
            $this->cartRepository->clearCart($cartId);
            $this->recalculateCartTotalPrice();
            DB::commit();

            return true;
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw CartException::clearCart();
        }

    }

    protected function clearCartFromCookies(): bool
    {
        Cookie::queue(Cookie::forget(self::COOKIE_CART_NAME));
        Cookie::queue(Cookie::forget(self::COOKIE_CART_ITEMS_NAME));

        return true;
    }

    public function getCartItemsCount(): int
    {
        if (Auth::check()) {
            return $this->cartRepository->getCartItemsCount(Auth::id());
        } else {
            return $this->getCart()->cartItems->count();
        }

    }

    public function moveCartItemsToDatabase(): void
    {
        $cookieCart = $this->getCartFromCookies();
        if ($cookieCart->cartItems->isEmpty()) {
            // getCartFromCookies() recalculates the cart it just built, which as a
            // side effect caches it as $cachedCart regardless of Auth state. Undo
            // that here so a later getCart() call in this same request correctly
            // re-resolves from the database instead of this stale empty guest cart.
            $this->cachedCart = null;

            return;
        }
        $dbCart = $this->getCartFromDatabase();
        $dbCartItems = $dbCart->cartItems;
        $itemsToCreate = [];
        $itemsToUpdate = [];
        $idsToUpdate = [];
        foreach ($cookieCart->cartItems as $cookieItem) {
            $attributes = $cookieItem->attributes ?? [];
            ksort($attributes);
            $existingItem = $this->findExistingCartItem($dbCartItems, $cookieItem->product_id, $attributes);
            if ($existingItem) {
                $newQuantity = $existingItem->quantity + $cookieItem->quantity;
                $totalPrice = $newQuantity * $existingItem->unit_price;
                $itemsToUpdate[] = [
                    'id' => $existingItem->id,
                    'quantity' => $newQuantity,
                    'total_price' => $totalPrice,
                    'attributes' => $attributes,
                ];
                $idsToUpdate[] = $existingItem->id;
            } else {
                $itemsToCreate[] = AddToCartDto::withAttributes(
                    $cookieItem->product_id,
                    $cookieItem->quantity,
                    $cookieItem->unit_price,
                    $attributes
                );
            }
        }
        if (! empty($itemsToCreate)) {
            $this->cartRepository->createCartItems($dbCart->id, $itemsToCreate);
        }
        if (! empty($itemsToUpdate)) {
            $this->cartRepository->batchUpdateCartItems($itemsToUpdate, $idsToUpdate);
        }
        $dbCart = $this->getCartFromDatabase();
        $this->clearCartFromCookies();
        $this->recalculateCartTotalPrice($dbCart);
    }

    protected function getFromCookies(string $cookieName): array|string|null
    {
        return request()->cookie($cookieName, '[]');
    }

    protected function getCartItemsForCookies($item): array
    {
        $attributeIds = $item->attributes ?? [];
        ksort($attributeIds);
        $key = $item->product_id.'_'.json_encode($attributeIds);

        $itemData = [
            'id' => $item->id_from_cookie ?? (string) Str::uuid(),
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'price' => $item->unit_price,
            'attribute_ids' => $attributeIds,
        ];
        $cartItemsForCookie[$key] = $itemData;

        return $cartItemsForCookie;
    }

    /**
     * @param  AddToCartDto[]  $addToCartRequests
     */
    protected function fetchProductsToBeAdded(array $addToCartRequests): Collection
    {
        $productIds = collect($addToCartRequests)->map(fn ($request): int => $request->getProductId())->all();

        return $this->productService->findProductsByIds($productIds);
    }

    /**
     * @param  Collection<int, Product>  $productsToBeAdded
     */
    protected function setAttributesIfEmptyToRequest(AddToCartDto $request, Collection $productsToBeAdded): void
    {
        if (empty($request->getAttributes())) {
            $product = $productsToBeAdded->find($request->getProductId());
            if ($product && $product->attributes->isNotEmpty()) {
                $defaultAttributes = [];
                foreach ($product->attributes as $attribute) {
                    if ($attribute->attributeOptions->isNotEmpty()) {
                        $defaultAttributes[$attribute->id] = $attribute->attributeOptions->first()->id;
                    }
                }
                $request->setAttributes($defaultAttributes);
            }
        }
    }

    private function calculatePriceWithAttributes(Product $product, array $attributes): float
    {
        $newPrice = $product->price;
        $attributeValues = $product->productAttributeValues;

        foreach ($attributes as $attributeId => $optionId) {
            $value = $attributeValues->first(function ($item) use ($attributeId, $optionId) {
                return $item->attribute_id == $attributeId && $item->attribute_option_id == $optionId;
            });

            if ($value) {
                if ($value->attribute_value_method === 'attribute.value.method.fixed') {
                    $newPrice += (float) $value->attribute_value;
                } elseif ($value->attribute_value_method === 'attribute.value.method.percent') {
                    $newPrice *= (1 + (float) $value->attribute_value / 100);
                }
            }
        }

        return $newPrice;
    }

    protected function recalculateCartTotalPrice(?Cart $cart = null): void
    {
        if ($cart === null) {
            $cart = $this->getCart();
        }
        $cart->recalculateCartTotalPrice();
        if (Auth::check()) {
            $this->cartRepository->saveCart($cart);
        } else {
            $cartAttributes = collect($cart->toArray())->only($cart->getFillable())->toArray();
            $this->putCartInCookies($cartAttributes);
        }
        $this->cachedCart = $cart;
    }

    /**
     * @param  Collection<int, CartItem>  $cartItems
     */
    protected function findExistingCartItemForUpdate(Collection $cartItems, string $cartItemId): ?CartItem
    {
        if ($cartItems->isEmpty()) {
            return null;
        }

        return $cartItems->first(function (CartItem $cartItem) use ($cartItemId) {
            return (isset($cartItem->id_from_cookie) && $cartItem->id_from_cookie === $cartItemId) ||
                   (isset($cartItem->id) && (string) $cartItem->id === $cartItemId);
        });
    }

    protected function putCartInCookies(array $cartAttributes): void
    {
        Cookie::queue(self::COOKIE_CART_NAME, json_encode($cartAttributes), self::COOKIE_LIFETIME);
    }

    protected function putItemsToCookies(array $cartItemsForCookie): void
    {
        Cookie::queue(self::COOKIE_CART_ITEMS_NAME, json_encode(array_values($cartItemsForCookie)), self::COOKIE_LIFETIME);
    }
}
