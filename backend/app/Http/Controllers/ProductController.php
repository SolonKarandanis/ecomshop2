<?php

namespace App\Http\Controllers;

use App\Data\ProductData;
use App\Data\ReviewData;
use App\Dtos\ProductSearchFilterDto;
use App\Http\Requests\ProductSearchRequest;
use App\Services\ProductService;
use App\Services\ReviewService;
use Spatie\LaravelData\PaginatedDataCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ReviewService $reviewService,
    ) {}

    public function index(ProductSearchRequest $request): PaginatedDataCollection|array
    {
        $dto = ProductSearchFilterDto::fromRequest($request);

        return ProductData::collect($this->productService->searchProducts($dto), PaginatedDataCollection::class);
    }

    // Embeds the first page of published reviews so the product page renders in
    // one round-trip; later pages come from GET /products/{product}/reviews.
    public function show(string $slug): ProductData
    {
        $product = $this->productService->getProductBySlug($slug);
        $reviews = $this->reviewService->getPublishedReviewsForProduct($product->id);

        return ProductData::from($product)
            ->additional(['reviews' => ReviewData::collect($reviews, PaginatedDataCollection::class)])
            ->wrap('data');
    }
}
