<?php

namespace App\Dtos;

class ReviewRatingStatsDto
{
    private ?float $averageRating;
    private int $reviewsCount;

    public function __construct(?float $averageRating, int $reviewsCount)
    {
        $this->averageRating = $averageRating;
        $this->reviewsCount = $reviewsCount;
    }

    public function getAverageRating(): ?float
    {
        return $this->averageRating;
    }

    public function getReviewsCount(): int
    {
        return $this->reviewsCount;
    }
}
