<?php

namespace App\Dtos;

class UpdateCartItemsDTO
{
    private int|string $cartItemId;
    private int $productId;
    private int $quantity;
    private array $attributes;

    public function __construct(int|string $cartItemId,int $productId,int $quantity,array $attributes){
        $this->cartItemId = $cartItemId;
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->attributes = $attributes;
    }


    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): void
    {
        $this->productId = $productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getCartItemId(): int|string
    {
        return $this->cartItemId;
    }

    public function setCartItemId(int|string $cartItemId): void
    {
        $this->cartItemId = $cartItemId;
    }


}
