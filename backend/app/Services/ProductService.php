<?php

namespace App\Services;

use App\Dtos\ProductSearchFilterDto;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Search\ProductSearchEngineFactory;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

#[Singleton]
class ProductService
{

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductSearchEngineFactory $searchEngineFactory,
    ){}

    public function getProductById(int $id): Product{
        return $this->productRepository->getProductById($id);
    }

    public function getProductBySlug($slug): Product{
        return $this->productRepository->getProductBySlug($slug);
    }

    public function searchProducts(ProductSearchFilterDto $dto): LengthAwarePaginator|array{
        return $this->searchEngineFactory
            ->make()
            ->search($dto);
    }

    public function findProductsByIds(array $productIds): Collection{
        return $this->productRepository->findProductsByIds($productIds);
    }

    public function findProductsForCart(array $productIds): Collection{
        return $this->productRepository->findProductsForCart($productIds);
    }

    public function updateRatingStats(int $productId, ?float $averageRating, int $reviewsCount): bool{
        return $this->productRepository->updateRatingStats($productId, $averageRating, $reviewsCount);
    }

    public function lockForUpdate(int $productId): Product{
        return $this->productRepository->lockForUpdate($productId);
    }
}
