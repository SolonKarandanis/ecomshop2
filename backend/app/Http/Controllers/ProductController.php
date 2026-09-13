<?php

namespace App\Http\Controllers;

use App\Data\ProductData;
use App\Dtos\ProductSearchFilterDto;
use App\Http\Requests\ProductSearchRequest;
use App\Services\ProductService;
use Spatie\LaravelData\PaginatedDataCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(ProductSearchRequest $request): PaginatedDataCollection|array
    {
        $dto = ProductSearchFilterDto::fromRequest($request);

        return ProductData::collect($this->productService->searchProducts($dto), PaginatedDataCollection::class);
    }

    public function show(string $slug): ProductData
    {
        return ProductData::from($this->productService->getProductBySlug($slug))->wrap('data');
    }
}
