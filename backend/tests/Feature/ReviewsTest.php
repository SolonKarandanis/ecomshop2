<?php

use App\Enums\OrderStatusEnum;
use App\Enums\ReviewStatusEnum;
use App\Enums\RolesEnum;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Spatie\Permission\Models\Role;

function reviewsBuyer(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    return $buyer;
}

function reviewsAdmin(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create();
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    return $admin;
}

function reviewsSupplier(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create();
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);

    return $supplier;
}

function giveDeliveredOrder(User $buyer, Product $product): Order
{
    $order = Order::factory()->create(['user_id' => $buyer->id, 'order_status' => OrderStatusEnum::Delivered->value]);
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_amount' => $product->price,
        'total_amount' => $product->price,
    ]);

    return $order;
}

it('rejects submitting a Review without a Verified Purchase', function () {
    $buyer = reviewsBuyer();
    $product = Product::factory()->create();

    $this->actingAs($buyer)
        ->postJson("/products/{$product->id}/reviews", ['rating' => 5, 'comment' => 'Great!'])
        ->assertStatus(400);

    $this->assertDatabaseMissing('reviews', ['user_id' => $buyer->id, 'product_id' => $product->id]);
});

it('lets a Buyer with a Verified Purchase submit an auto-published Review', function () {
    $buyer = reviewsBuyer();
    $product = Product::factory()->create();
    giveDeliveredOrder($buyer, $product);

    $response = $this->actingAs($buyer)
        ->postJson("/products/{$product->id}/reviews", ['rating' => 4, 'comment' => 'Nice product'])
        ->assertCreated();

    expect($response->json('data.status'))->toBe(ReviewStatusEnum::PUBLISHED->value);
    $this->assertDatabaseHas('reviews', [
        'user_id' => $buyer->id,
        'product_id' => $product->id,
        'rating' => 4,
        'status' => ReviewStatusEnum::PUBLISHED->value,
    ]);
});

it("updates the Product's Average Rating and cached Review count when a Review is submitted", function () {
    $buyer = reviewsBuyer();
    $product = Product::factory()->create();
    giveDeliveredOrder($buyer, $product);

    $this->actingAs($buyer)->postJson("/products/{$product->id}/reviews", ['rating' => 4])->assertCreated();

    $product->refresh();
    expect((float) $product->average_rating)->toBe(4.0);
    expect($product->reviews_count)->toBe(1);
});

it('edits the existing Review instead of creating a duplicate on a second submit attempt', function () {
    $buyer = reviewsBuyer();
    $product = Product::factory()->create();
    giveDeliveredOrder($buyer, $product);

    $this->actingAs($buyer)->postJson("/products/{$product->id}/reviews", ['rating' => 3, 'comment' => 'ok'])->assertCreated();
    $response = $this->actingAs($buyer)->postJson("/products/{$product->id}/reviews", ['rating' => 5, 'comment' => 'actually great'])->assertOk();

    expect($response->json('data.rating'))->toBe(5);
    expect(Review::where('user_id', $buyer->id)->where('product_id', $product->id)->count())->toBe(1);
    $this->assertDatabaseHas('reviews', ['user_id' => $buyer->id, 'product_id' => $product->id, 'rating' => 5, 'comment' => 'actually great']);

    $product->refresh();
    expect((float) $product->average_rating)->toBe(5.0);
    expect($product->reviews_count)->toBe(1);
});

it('rejects a Buyer submitting a second, invalid rating on their existing Review', function () {
    $buyer = reviewsBuyer();
    $product = Product::factory()->create();
    giveDeliveredOrder($buyer, $product);

    $this->actingAs($buyer)->postJson("/products/{$product->id}/reviews", ['rating' => 3])->assertCreated();

    $this->actingAs($buyer)->postJson("/products/{$product->id}/reviews", ['rating' => 9])->assertUnprocessable();
});

it('rejects a non-Buyer (Admin or Supplier) from submitting a Review', function () {
    $product = Product::factory()->create();
    $admin = reviewsAdmin();
    $supplier = reviewsSupplier();

    $this->actingAs($admin)->postJson("/products/{$product->id}/reviews", ['rating' => 5])->assertForbidden();
    $this->actingAs($supplier)->postJson("/products/{$product->id}/reviews", ['rating' => 5])->assertForbidden();
});

it('rejects a guest from submitting a Review', function () {
    $product = Product::factory()->create();

    $this->postJson("/products/{$product->id}/reviews", ['rating' => 5])->assertUnauthorized();
});

it('returns 404 when submitting a Review for a nonexistent Product', function () {
    $buyer = reviewsBuyer();

    $this->actingAs($buyer)->postJson('/products/999999/reviews', ['rating' => 5])->assertNotFound();
});

it('lists only published Reviews for a Product, publicly and without authentication', function () {
    $product = Product::factory()->create();
    $publishedReview = Review::factory()->create(['product_id' => $product->id, 'status' => ReviewStatusEnum::PUBLISHED->value]);
    Review::factory()->create(['product_id' => $product->id, 'status' => ReviewStatusEnum::HIDDEN->value]);

    $response = $this->getJson("/products/{$product->id}/reviews")->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($publishedReview->id);
});

it('lets an Admin hide a Review, excluding it from public display and the Average Rating', function () {
    $admin = reviewsAdmin();
    $product = Product::factory()->create();
    $buyer = reviewsBuyer();
    giveDeliveredOrder($buyer, $product);
    $this->actingAs($buyer)->postJson("/products/{$product->id}/reviews", ['rating' => 2])->assertCreated();
    $review = Review::where('user_id', $buyer->id)->where('product_id', $product->id)->firstOrFail();

    $response = $this->actingAs($admin)
        ->patchJson("/products/{$product->id}/reviews/{$review->id}/hide")
        ->assertOk();

    expect($response->json('data.status'))->toBe(ReviewStatusEnum::HIDDEN->value);

    $this->getJson("/products/{$product->id}/reviews")->assertOk()->assertJsonCount(0, 'data');

    $product->refresh();
    expect($product->average_rating)->toBeNull();
    expect($product->reviews_count)->toBe(0);
});

it('rejects a Buyer or Supplier from hiding a Review', function () {
    $product = Product::factory()->create();
    $review = Review::factory()->create(['product_id' => $product->id]);
    $buyer = reviewsBuyer();
    $supplier = reviewsSupplier();

    $this->actingAs($buyer)->patchJson("/products/{$product->id}/reviews/{$review->id}/hide")->assertForbidden();
    $this->actingAs($supplier)->patchJson("/products/{$product->id}/reviews/{$review->id}/hide")->assertForbidden();
});

it('rejects a guest from hiding a Review', function () {
    $product = Product::factory()->create();
    $review = Review::factory()->create(['product_id' => $product->id]);

    $this->patchJson("/products/{$product->id}/reviews/{$review->id}/hide")->assertUnauthorized();
});

it('returns 404 when an Admin hides a nonexistent Review', function () {
    $admin = reviewsAdmin();
    $product = Product::factory()->create();

    $this->actingAs($admin)->patchJson("/products/{$product->id}/reviews/999999/hide")->assertNotFound();
});

it("returns 404 when hiding a Review through a Product it doesn't belong to", function () {
    $admin = reviewsAdmin();
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $review = Review::factory()->create(['product_id' => $product->id]);

    $this->actingAs($admin)->patchJson("/products/{$otherProduct->id}/reviews/{$review->id}/hide")->assertNotFound();

    $review->refresh();
    expect($review->status)->toBe(ReviewStatusEnum::PUBLISHED->value);
});
