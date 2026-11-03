<?php

namespace App\Http\Controllers;

use App\Data\MyReviewData;
use App\Data\ReviewData;
use App\Dtos\SubmitReviewDto;
use App\Dtos\UpdateReviewDTO;
use App\Enums\ReviewStatusEnum;
use App\Http\Requests\ReviewSearchRequest;
use App\Http\Requests\SubmitReviewRequest;
use App\Services\ProductService;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly ProductService $productService,
    ) {}

    public function index(ReviewSearchRequest $request, int $product): PaginatedDataCollection
    {
        $this->productService->getProductById($product);

        $perPage = $request->integer('per_page', 5);

        return ReviewData::collect($this->reviewService->getPublishedReviewsForProduct($product, $perPage), PaginatedDataCollection::class);
    }

    // Only Buyers review (the `buyer-action` gate on store), so anyone else
    // gets a plain "no" rather than a 403 the page would have to handle.
    public function mine(Request $request, int $product): MyReviewData
    {
        $this->productService->getProductById($product);

        $user = $request->user();
        if (! $user->isBuyer()) {
            return (new MyReviewData(can_review: false, review: null))->wrap('data');
        }

        $review = $this->reviewService->getReviewForBuyer($user->id, $product);

        return (new MyReviewData(
            can_review: $review === null && $this->reviewService->canReview($user->id, $product),
            review: $review ? ReviewData::from($review) : null,
        ))->wrap('data');
    }

    public function store(SubmitReviewRequest $request, int $product): ReviewData
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

        return ReviewData::from($review)->wrap('data');
    }

    public function hide(int $product, int $review): ReviewData
    {
        Gate::authorize('admin-action');

        $reviewModel = $this->reviewService->getReviewOrFail($review);
        abort_unless($reviewModel->product_id === $product, 404);

        return ReviewData::from($this->reviewService->updateReviewStatus($reviewModel, ReviewStatusEnum::HIDDEN))->wrap('data');
    }
}
