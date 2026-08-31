<?php

namespace App\Dtos;

use App\Http\Requests\SubmitReviewRequest;

class UpdateReviewDTO
{
    private int $userId;
    private int $reviewId;
    private int $productId;
    private int $rating;
    private ?string $comment;

    public function __construct(int $userId,int $reviewId,int $productId, int $rating, ?string $comment)
    {
        $this->userId = $userId;
        $this->reviewId = $reviewId;
        $this->productId = $productId;
        $this->rating = $rating;
        $this->comment = $comment;
    }

    public static function fromRequest(SubmitReviewRequest $request,int $reviewId, int $userId,int $productId): self
    {
        return new self(
            $userId,
            $reviewId,
            $productId,
            $request->input('rating'),
            $request->input('comment')
        );
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getReviewId(): int
    {
        return $this->reviewId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }
}
