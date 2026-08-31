<?php

namespace App\Repositories;

use App\Dtos\ReviewRatingStatsDto;
use App\Dtos\SubmitReviewDto;
use App\Dtos\UpdateReviewDTO;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReviewRepository
{
    public function modelQuery(): Builder|Review
    {
        return Review::query();
    }

    public function getReviewById(int $id): ?Review
    {
        return $this->modelQuery()->find($id);
    }

    public function findByUserAndProduct(int $userId, int $productId): ?Review
    {
        return $this->modelQuery()->where('user_id', $userId)->where('product_id', $productId)->first();
    }

    public function createReview(SubmitReviewDto $dto): Review
    {
        return $this->modelQuery()->create([
            'user_id' => $dto->getUserId(),
            'product_id' => $dto->getProductId(),
            'rating' => $dto->getRating(),
            'comment' => $dto->getComment(),
        ]);
    }

    public function updateReview(Review $review, UpdateReviewDTO $dto): bool
    {
        return $review->update([
            'rating' => $dto->getRating(),
            'comment' => $dto->getComment(),
        ]);
    }

    public function updateStatus(Review $review, string $status): bool
    {
        return $review->update([
            'status' => $status,
        ]);
    }

    public function updateAdminReply(Review $review, ?string $adminReply): bool
    {
        return $review->update([
            'admin_reply' => $adminReply,
        ]);
    }

    public function getPublishedReviewsForProduct(int $productId): LengthAwarePaginator|Collection
    {
        return $this->modelQuery()->where('product_id', $productId)->published()->paginate(5);
    }

    public function getRatingStatsForProduct(int $productId): ReviewRatingStatsDto
    {
        $stats = $this->modelQuery()
            ->where('product_id', $productId)
            ->published()
            ->selectRaw('AVG(rating) as average_rating, COUNT(*) as reviews_count')
            ->first();

        return new ReviewRatingStatsDto(
            $stats->average_rating !== null ? (float) $stats->average_rating : null,
            (int) $stats->reviews_count
        );
    }

    public function existsByUserIdAndReviewId(int $userId, int $reviewId): bool
    {
        return $this->modelQuery()
            ->where('user_id', $userId)
            ->where('id', $reviewId)
            ->exists();
    }
}
