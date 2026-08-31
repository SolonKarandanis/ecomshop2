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
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * @throws ReviewException|Throwable
     */
    public function submitReview(SubmitReviewDto $dto): Review
    {
        if (! $this->canReview($dto->getUserId(), $dto->getProductId())) {
            throw ReviewException::notEligible();
        }
        try {
            DB::beginTransaction();
            $this->productService->lockForUpdate($dto->getProductId());
            $review = $this->reviewRepository->createReview($dto);
            $stats = $this->reviewRepository->getRatingStatsForProduct($dto->getProductId());
            $this->productService->updateRatingStats($dto->getProductId(), $stats->getAverageRating(), $stats->getReviewsCount());
            DB::commit();

            return $review;
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw ReviewException::createReview();
        }
    }

    /**
     * @throws ReviewException
     */
    public function updateReview(UpdateReviewDTO $dto): Review
    {
        if (! $this->canEdit($dto->getUserId(), $dto->getReviewId())) {
            throw ReviewException::notEligible();
        }
        try {
            DB::beginTransaction();
            $this->productService->lockForUpdate($dto->getProductId());
            $review = $this->reviewRepository->getReviewById($dto->getReviewId());
            $this->reviewRepository->updateReview($review, $dto);
            $stats = $this->reviewRepository->getRatingStatsForProduct($dto->getProductId());
            $this->productService->updateRatingStats($dto->getProductId(), $stats->getAverageRating(), $stats->getReviewsCount());
            $review = $this->reviewRepository->getReviewById($dto->getReviewId());
            DB::commit();

            return $review;
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw ReviewException::updateReview();
        }

    }

    public function getPublishedReviewsForProduct(int $productId): LengthAwarePaginator|Collection
    {
        return $this->reviewRepository->getPublishedReviewsForProduct($productId);
    }

    /**
     * @throws ReviewException
     */
    public function updateReviewStatus(Review $review, ReviewStatusEnum $status): Review
    {
        try {
            DB::beginTransaction();
            $this->productService->lockForUpdate($review->product_id);
            $this->reviewRepository->updateStatus($review, $status->value);
            $stats = $this->reviewRepository->getRatingStatsForProduct($review->product_id);
            $this->productService->updateRatingStats($review->product_id, $stats->getAverageRating(), $stats->getReviewsCount());
            DB::commit();

            return $review->refresh();
        } catch (Throwable $exception) {
            Log::error($exception);
            DB::rollBack();
            throw ReviewException::updateStatus();
        }
    }

    /**
     * @throws ReviewException
     */
    public function updateAdminReply(Review $review, ?string $adminReply): Review
    {
        try {
            $this->reviewRepository->updateAdminReply($review, $adminReply);

            return $review->refresh();
        } catch (Throwable $exception) {
            Log::error($exception);
            throw ReviewException::updateAdminReply();
        }
    }
}
