<?php

namespace App\Data;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReviewData extends BaseData
{
    private bool $wasRecentlyCreated = false;

    public function __construct(
        public int $id,
        public int $product_id,
        public int $user_id,
        public int $rating,
        public ?string $comment,
        public string $status,
        public ?string $admin_reply,
        public ?Carbon $created_at,
    ) {}

    public static function fromModel(Review $review): self
    {
        $data = new self(
            id: $review->id,
            product_id: $review->product_id,
            user_id: $review->user_id,
            rating: $review->rating,
            comment: $review->comment,
            status: $review->status,
            admin_reply: $review->admin_reply,
            created_at: $review->created_at,
        );

        $data->wasRecentlyCreated = $review->wasRecentlyCreated;

        return $data;
    }

    // A Review submission either creates a new Review or edits the Buyer's
    // existing one in place (see ReviewController::store); only the former
    // should read as a 201, matching the prior JsonResource-based behaviour.
    protected function calculateResponseStatus(Request $request): int
    {
        return $this->wasRecentlyCreated ? 201 : 200;
    }
}
