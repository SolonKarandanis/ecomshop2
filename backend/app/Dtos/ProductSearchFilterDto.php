<?php

namespace App\Dtos;

class ProductSearchFilterDto
{

    private array $selected_categories;
    private array $selected_brands;

    private bool $featured=false;
    private bool $on_sale=false;

    private int|null $price_from;
    private int|null $price_to;

    private string $sort;

    private string $search='';

    public function getSelectedCategories(): array
    {
        return $this->selected_categories;
    }

    public function setSelectedCategories(array $selected_categories): void
    {
        $this->selected_categories = $selected_categories;
    }

    public function getSelectedBrands(): array
    {
        return $this->selected_brands;
    }

    public function setSelectedBrands(array $selected_brands): void
    {
        $this->selected_brands = $selected_brands;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): void
    {
        $this->featured = $featured;
    }

    public function isOnSale(): bool
    {
        return $this->on_sale;
    }

    public function setOnSale(bool $on_sale): void
    {
        $this->on_sale = $on_sale;
    }

    public function getPriceFrom(): int
    {
        return $this->price_from;
    }

    public function setPriceFrom(int $price_from): void
    {
        $this->price_from = $price_from;
    }

    public function getPriceTo(): int
    {
        return $this->price_to;
    }

    public function setPriceTo(int $price_to): void
    {
        $this->price_to = $price_to;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function setSearch(string $search): void
    {
        $this->search = $search;
    }


}
