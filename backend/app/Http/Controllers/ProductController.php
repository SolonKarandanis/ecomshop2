<?php

namespace App\Http\Controllers;

use App\Dtos\ProductSearchFilterDto;
use App\Http\Requests\ProductSearchRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(ProductSearchRequest $request): AnonymousResourceCollection
    {
        $dto = ProductSearchFilterDto::fromRequest($request);

        return ProductResource::collection($this->productService->searchProducts($dto));
    }

    public function show(string $slug): ProductResource
    {
        return new ProductResource($this->productService->getProductBySlug($slug));
    }
}
