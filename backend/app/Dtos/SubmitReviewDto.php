<?php

namespace App\Dtos;

use App\Http\Requests\SubmitReviewRequest;

class SubmitReviewDto
{
    private int $productId;
    private int $userId;
    private int $rating;
    private ?string $comment;

    public function __construct(int $productId, int $userId, int $rating, ?string $comment = null)
    {
        $this->productId = $productId;
        $this->userId = $userId;
        $this->rating = $rating;
        $this->comment = $comment;
    }

    public static function fromRequest(SubmitReviewRequest $request,int $productId, int $userId): self
    {
        return new self(
            $productId,
            $userId,
            $request->input('rating'),
            $request->input('comment')
        );
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getUserId(): int
    {
        return $this->userId;
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
