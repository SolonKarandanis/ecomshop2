<?php

namespace App\Services;

use App\Dtos\ProductSearchFilterDto;
use App\Dtos\SaveProductDto;
use App\Exceptions\ProductException;
use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Search\ProductSearchEngineFactory;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function getSupplierProducts(int $supplierId, int $perPage): LengthAwarePaginator
    {
        return $this->productRepository->getProductsBySupplier($supplierId, $perPage);
    }

    // 404 when the Product doesn't exist, 403 when it belongs to another Supplier.
    public function getProductForSupplier(int $productId, User $supplier): Product
    {
        $product = $this->productRepository->getProductForManagement($productId);
        abort_unless($product->supplier_id === $supplier->id, 403);

        return $product;
    }

    public function createSupplierProduct(SaveProductDto $dto, User $supplier): Product
    {
        $fields = $dto->getFields();
        $fields['slug'] = ($fields['slug'] ?? null) ?: $this->uniqueSlugFrom($fields['name']);
        $fields['supplier_id'] = $supplier->id;
        $fields['is_featured'] = false;

        $product = DB::transaction(function () use ($fields, $dto) {
            $product = $this->productRepository->create($fields);
            $this->productRepository->syncAttributeValues($product, $dto->getAttributeValues() ?? []);

            return $product;
        });

        return $this->productRepository->getProductForManagement($product->id);
    }

    public function updateSupplierProduct(Product $product, SaveProductDto $dto): Product
    {
        DB::transaction(function () use ($product, $dto) {
            $this->productRepository->update($product, $dto->getFields());
            if ($dto->getAttributeValues() !== null) {
                $this->productRepository->syncAttributeValues($product, $dto->getAttributeValues());
            }
        });

        return $this->productRepository->getProductForManagement($product->id);
    }

    /**
     * Order items reference their Product with a cascading foreign key, so deleting
     * an ordered Product would delete lines from existing Orders. Suppliers deactivate
     * those instead.
     *
     * @throws ProductException
     */
    public function deleteSupplierProduct(Product $product): void
    {
        if ($this->productRepository->hasOrderItems($product->id)) {
            throw ProductException::orderedProductCannotBeDeleted();
        }

        DB::transaction(fn () => $this->productRepository->delete($product));
    }

    /**
     * @param  UploadedFile[]  $files
     *
     * @throws ValidationException
     */
    public function addProductImages(Product $product, array $files): Product
    {
        $existing = $product->getMedia(Product::IMAGES_COLLECTION)->count();
        if ($existing + count($files) > Product::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => __('messages.supplier_products.too_many_images', ['max' => Product::MAX_IMAGES]),
            ]);
        }

        foreach ($files as $file) {
            $product->addMedia($file)->toMediaCollection(Product::IMAGES_COLLECTION, 'media');
        }

        return $this->productRepository->getProductForManagement($product->id);
    }

    public function removeProductImage(Product $product, int $mediaId): Product
    {
        $media = $product->getMedia(Product::IMAGES_COLLECTION)->firstWhere('id', $mediaId);
        abort_if($media === null, 404);

        $media->delete();

        return $this->productRepository->getProductForManagement($product->id);
    }

    private function uniqueSlugFrom(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        for ($suffix = 2; $this->productRepository->slugExists($slug); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
