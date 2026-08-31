<?php

namespace App\Repositories;

use App\Dtos\AddToCartDto;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CartRepository
{
    public function modelQuery(): Builder|Cart
    {
        return Cart::query();
    }

    public function itemModelQuery(): Builder|CartItem
    {
        return CartItem::query();
    }

    public function getCart(int $userId): Cart
    {
        return $this->modelQuery()
            ->with([
                'cartItems',
                'cartItems.product',
                'cartItems.product.productAttributeValues.attribute',
                'cartItems.product.productAttributeValues.media',
            ])
            ->firstOrCreate(
                ['user_id' => $userId],
                ['total_price' => 0]
            );
    }

    public function getCartId(int $userId): int
    {
        $cart = $this->modelQuery()
            ->firstOrCreate(
                ['user_id' => $userId],
                ['total_price' => 0]
            );

        return $cart->id;
    }

    public function getDistinctSupplierIds(int $cartId): array
    {
        return $this->itemModelQuery()
            ->join('products', 'products.id', '=', 'cart_items.product_id')
            ->where('cart_items.cart_id', $cartId)
            ->distinct()
            ->pluck('products.supplier_id')
            ->all();
    }

    public function getCartItemsCount(int $userId): int
    {
        return $this->itemModelQuery()
            ->whereIn('cart_id', function ($query) use ($userId) {
                $query->select('id')
                    ->from('carts')
                    ->where('user_id', $userId);
            })
            ->count();
    }

    public function saveCart(Cart $cart): void
    {
        $cart->update($cart->getFillable());
    }

    public function findItemByProductIdAndAttributes(int $cartId, int $productId, array $attributes): ?CartItem
    {
        $attributesJson = json_encode($attributes);

        return $this->itemModelQuery()
            ->where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->where('attributes', $attributesJson)
            ->first();
    }

    public function updateItemQuantity(int $cartItemId, int $quantity, int $unitPrice, int $totalPrice): void
    {
        $this->itemModelQuery()->where('id', $cartItemId)->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
        ]);
    }

    public function createCartItem(int $cartId, AddToCartDto $addToCartDto): void
    {
        $total_price = $addToCartDto->getQuantity() * $addToCartDto->getPrice();
        $this->itemModelQuery()->create([
            'cart_id' => $cartId,
            'product_id' => $addToCartDto->getProductId(),
            'quantity' => $addToCartDto->getQuantity(),
            'unit_price' => $addToCartDto->getPrice(),
            'total_price' => $total_price,
            'attributes' => $addToCartDto->getAttributes(),
        ]);
    }

    public function deleteCartItem(int $cartId, int $cartItemId): void
    {
        $this->itemModelQuery()
            ->where('cart_id', $cartId)
            ->where('id', $cartItemId)
            ->delete();
    }

    public function deleteCartItems(int $cartId, array $cartItemIds): void
    {
        $this->itemModelQuery()
            ->where('cart_id', $cartId)
            ->whereIn('id', $cartItemIds)
            ->delete();
    }

    public function clearCart(int $cartId): void
    {
        $this->itemModelQuery()
            ->where('cart_id', $cartId)
            ->delete();
    }

    /**
     * Creates multiple cart items from an array of AddToCartDto objects.
     *
     * @param  AddToCartDto[]  $cartItems
     */
    public function createCartItems(int $cartId, array $cartItems): void
    {
        $itemsToInsert = collect($cartItems)->map(fn (AddToCartDto $dto) => [
            'cart_id' => $cartId,
            'product_id' => $dto->getProductId(),
            'quantity' => $dto->getQuantity(),
            'unit_price' => $dto->getPrice(),
            'total_price' => $dto->getQuantity() * $dto->getPrice(),
            'attributes' => json_encode($dto->getAttributes()),
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if (! empty($itemsToInsert)) {
            $this->itemModelQuery()->insert($itemsToInsert);
        }
    }

    /**
     * Updates quantities for multiple cart items.
     *
     * @param  array  $cartItems  Array of items with 'id' and 'quantity' to add.
     */
    public function updateCartItemsQuantity(array $cartItems): void
    {
        collect($cartItems)->each(
            fn ($item) => $this->itemModelQuery()->where('id', $item['id'])->increment('quantity', $item['quantity'])
        );
    }

    public function updateCartItem(CartItem $cartItem): void
    {
        $this->itemModelQuery()->update($cartItem->toArray());
    }

    public function batchUpdateCartItems(array $updates, array $idsToUpdate): void
    {
        if (empty($updates)) {
            return;
        }

        $table = (new CartItem)->getTable();

        $whenThen = str_repeat('WHEN ? THEN ? ', count($updates));
        $quantityCase = "quantity = CASE id {$whenThen}END";
        $totalPriceCase = "total_price = CASE id {$whenThen}END";
        $attributesCase = "attributes = CASE id {$whenThen}END";

        $params = collect($updates)->flatMap(fn ($update) => [
            $update['id'], $update['quantity'],
            $update['id'], $update['total_price'],
            $update['id'], is_array($update['attributes']) ? json_encode($update['attributes']) : $update['attributes'],
        ])->all();

        $ids = implode(',', array_fill(0, count($idsToUpdate), '?'));

        $sql = "UPDATE {$table} SET {$quantityCase}, {$totalPriceCase}, {$attributesCase} WHERE id IN ({$ids})";

        $bindings = collect($params)->concat($idsToUpdate)->all();

        DB::update($sql, $bindings);
    }
}
