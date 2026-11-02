<?php

namespace App\Services;

use App\Dtos\SubmitReviewDto;
use App\Dtos\UpdateReviewDTO;
use App\Enums\ReviewStatusEnum;
use App\Exceptions\ReviewException;
use App\Models\Review;
use App\Repositories\OrderRepository;
use App\Repositories\ReviewRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Singleton]
class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly OrderRepository $orderRepository,
        private readonly ProductService $productService,
    ) {}

    public function canReview(int $userId, int $productId): bool
    {
        if ($this->reviewRepository->findByUserAndProduct($userId, $productId)) {
            return false;
        }

        return $this->orderRepository->hasDeliveredOrderForProduct($userId, $productId);
    }

    public function canEdit(int $userId, int $reviewId): bool
    {
        return $this->reviewRepository->existsByUserIdAndReviewId($userId, $reviewId);
    }

    public function getReviewForBuyer(int $userId, int $productId): ?Review
    {
        return $this->reviewRepository->findByUserAndProduct($userId, $productId);
    }

    public function getReviewOrFail(int $reviewId): Review
    {
        $review = $this->reviewRepository->getReviewById($reviewId);
        abort_if($review === null, 404);

        return $review;
    }

    /**
     * @throws ReviewException
     */
    public function submitReview(SubmitReviewDto $dto): Review
    {
        if (! $this->canReview($dto->getUserId(), $dto->getProductId())) {
            throw ReviewException::notEligible();
        }

        return DB::transaction(function () use ($dto) {
            $this->productService->lockForUpdate($dto->getProductId());
            $review = $this->reviewRepository->createReview($dto);
            $stats = $this->reviewRepository->getRatingStatsForProduct($dto->getProductId());
            $this->productService->updateRatingStats($dto->getProductId(), $stats->getAverageRating(), $stats->getReviewsCount());

            return $review;
        });
    }

    /**
     * @throws ReviewException
     */
    public function updateReview(UpdateReviewDTO $dto): Review
    {
        if (! $this->canEdit($dto->getUserId(), $dto->getReviewId())) {
            throw ReviewException::notEligible();
        }

        return DB::transaction(function () use ($dto) {
            $this->productService->lockForUpdate($dto->getProductId());
            $review = $this->reviewRepository->getReviewById($dto->getReviewId());
            $this->reviewRepository->updateReview($review, $dto);
            $stats = $this->reviewRepository->getRatingStatsForProduct($dto->getProductId());
            $this->productService->updateRatingStats($dto->getProductId(), $stats->getAverageRating(), $stats->getReviewsCount());

            return $this->reviewRepository->getReviewById($dto->getReviewId());
        });
    }

    public function getPublishedReviewsForProduct(int $productId, int $perPage = 5): LengthAwarePaginator|Collection
    {
        return $this->reviewRepository->getPublishedReviewsForProduct($productId, $perPage);
    }

    public function updateReviewStatus(Review $review, ReviewStatusEnum $status): Review
    {
        return DB::transaction(function () use ($review, $status) {
            $this->productService->lockForUpdate($review->product_id);
            $this->reviewRepository->updateStatus($review, $status->value);
            $stats = $this->reviewRepository->getRatingStatsForProduct($review->product_id);
            $this->productService->updateRatingStats($review->product_id, $stats->getAverageRating(), $stats->getReviewsCount());

            return $review->refresh();
        });
    }

    public function updateAdminReply(Review $review, ?string $adminReply): Review
    {
        $this->reviewRepository->updateAdminReply($review, $adminReply);

        return $review->refresh();
    }
}
