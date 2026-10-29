<?php

namespace App\Dtos;

class AddToCartDto
{
    private int $productId;
    private int $quantity;
    private float $price;
    private array $attributes;

    public function __construct(int $productId, int $quantity, float $price){
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->attributes = [];
    }

    public static function withAttributes(int $productId, int $quantity, float $price, array $attributes): self
    {
        $dto = new self($productId, $quantity, $price);
        $dto->setAttributes($attributes);
        return $dto;
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

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
