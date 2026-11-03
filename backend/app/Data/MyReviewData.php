<?php

namespace App\Data;

// What the product page needs to decide which review form to show the
// logged-in user: the write form, their own review to edit, or neither.
class MyReviewData extends BaseData
{
    public function __construct(
        // A delivered Order containing the product, and no review of it yet.
        public bool $can_review,
        // Their existing review, whatever its status, so a hidden one can say so.
        public ?ReviewData $review,
    ) {}
}
