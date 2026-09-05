<?php

namespace App\Http\Controllers;

use App\Dtos\SubmitReviewDto;
use App\Dtos\UpdateReviewDTO;
use App\Enums\ReviewStatusEnum;
use App\Http\Requests\SubmitReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Services\ProductService;
use App\Services\ReviewService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly ProductService $productService,
    ) {}

    public function index(int $product): AnonymousResourceCollection
    {
        $this->productService->getProductById($product);

        return ReviewResource::collection($this->reviewService->getPublishedReviewsForProduct($product));
    }

    public function store(SubmitReviewRequest $request, int $product): ReviewResource
    {
        Gate::authorize('buyer-action');
        $this->productService->getProductById($product);

        $user = $request->user();
        $existing = $this->reviewService->getReviewForBuyer($user->id, $product);

        if ($existing !== null) {
            $dto = UpdateReviewDTO::fromRequest($request, $existing->id, $user->id, $product);
            $review = $this->reviewService->updateReview($dto);
        } else {
            $dto = SubmitReviewDto::fromRequest($request, $product, $user->id);
            $review = $this->reviewService->submitReview($dto);
        }

        return new ReviewResource($review);
    }

    public function hide(int $product, int $review): ReviewResource
    {
        Gate::authorize('admin-action');

        $reviewModel = $this->reviewService->getReviewOrFail($review);
        abort_unless($reviewModel->product_id === $product, 404);

        return new ReviewResource($this->reviewService->updateReviewStatus($reviewModel, ReviewStatusEnum::HIDDEN));
    }
}
