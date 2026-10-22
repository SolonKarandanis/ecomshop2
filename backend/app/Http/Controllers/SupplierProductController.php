<?php

namespace App\Http\Controllers;

use App\Data\SupplierProductData;
use App\Dtos\SaveProductDto;
use App\Http\Requests\StoreSupplierProductRequest;
use App\Http\Requests\SupplierProductSearchRequest;
use App\Http\Requests\UpdateSupplierProductRequest;
use App\Http\Requests\UploadProductImagesRequest;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class SupplierProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    // The FormRequest-backed actions check the `supplier-action` gate in authorize(),
    // so a Buyer gets 403 rather than validation errors.

    public function index(SupplierProductSearchRequest $request): PaginatedDataCollection|array
    {
        $products = $this->productService->getSupplierProducts($request->user()->id, $request->integer('perPage', 10));

        return SupplierProductData::collect($products, PaginatedDataCollection::class);
    }

    public function show(int $product, Request $request): SupplierProductData
    {
        abort_unless(Gate::allows('supplier-action'), 403);

        return SupplierProductData::from($this->productService->getProductForSupplier($product, $request->user()))->wrap('data');
    }

    public function store(StoreSupplierProductRequest $request): JsonResponse
    {
        $product = $this->productService->createSupplierProduct(SaveProductDto::fromRequest($request), $request->user());

        return SupplierProductData::from($product)
            ->wrap('data')
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function update(UpdateSupplierProductRequest $request, int $product): SupplierProductData
    {
        $productModel = $this->productService->getProductForSupplier($product, $request->user());
        $productModel = $this->productService->updateSupplierProduct($productModel, SaveProductDto::fromRequest($request));

        return SupplierProductData::from($productModel)->wrap('data');
    }

    public function destroy(int $product, Request $request): JsonResponse
    {
        abort_unless(Gate::allows('supplier-action'), 403);

        $productModel = $this->productService->getProductForSupplier($product, $request->user());
        $this->productService->deleteSupplierProduct($productModel);

        return response()->json(null, 204);
    }

    public function storeImages(UploadProductImagesRequest $request, int $product): SupplierProductData
    {
        $productModel = $this->productService->getProductForSupplier($product, $request->user());

        return SupplierProductData::from($this->productService->addProductImages($productModel, $request->file('images')))->wrap('data');
    }

    public function destroyImage(int $product, int $media, Request $request): SupplierProductData
    {
        abort_unless(Gate::allows('supplier-action'), 403);

        $productModel = $this->productService->getProductForSupplier($product, $request->user());

        return SupplierProductData::from($this->productService->removeProductImage($productModel, $media))->wrap('data');
    }
}
