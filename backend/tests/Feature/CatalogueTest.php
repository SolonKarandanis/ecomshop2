<?php

use App\Enums\ReviewStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists active products as a paginated resource collection', function () {
    Product::factory()->count(3)->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/products')->assertOk();

    expect($response->json('data'))->toHaveCount(3);
    $response->assertJsonStructure(['data', 'links', 'meta']);
});

it('filters products by category', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $matching = Product::factory()->create(['is_active' => true, 'category_id' => $categoryA->id]);
    Product::factory()->create(['is_active' => true, 'category_id' => $categoryB->id]);

    $response = $this->getJson('/api/products?categories[]='.$categoryA->id)->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

it('filters products by price range', function () {
    $cheap = Product::factory()->create(['is_active' => true, 'price' => 20]);
    Product::factory()->create(['is_active' => true, 'price' => 500]);

    $response = $this->getJson('/api/products?price_from=0&price_to=100')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($cheap->id);
});

it('searches products by name', function () {
    // On MySQL/MariaDB this is a FULLTEXT search, and InnoDB's FULLTEXT index only sees
    // committed rows, while RefreshDatabase keeps each test in a transaction. So commit
    // the Products, and delete them (and what their factory created) afterwards.
    DB::commit();
    $products = collect();

    try {
        $products->push($match = Product::factory()->create(['is_active' => true, 'name' => 'Wireless Keyboard']));
        $products->push(Product::factory()->create(['is_active' => true, 'name' => 'Desk Lamp']));

        $response = $this->getJson('/api/products?q=keyboard')->assertOk();

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.id'))->toBe($match->id);
    } finally {
        foreach ($products as $product) {
            $product->delete();
            $product->category->delete();
            $product->brand->delete();
            User::whereKey($product->supplier_id)->delete();
        }
        DB::beginTransaction();
    }
});

it('rejects an invalid sort value', function () {
    $this->getJson('/api/products?sort=bogus')->assertUnprocessable()->assertJsonValidationErrors('sort');
});

it('shows a single product by slug including its rating and review count', function () {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();
    $product = Product::factory()->create([
        'is_active' => true,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'average_rating' => 4.5,
        'reviews_count' => 12,
    ]);

    $this->getJson("/api/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.average_rating', 4.5)
        ->assertJsonPath('data.reviews_count', 12)
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.brand.id', $brand->id)
        ->assertJsonMissingPath('data.supplier_id');
});

it('returns 404 for an unknown product slug', function () {
    $this->getJson('/api/products/does-not-exist')->assertNotFound();
});

it('lists only active categories', function () {
    $active = Category::factory()->create(['is_active' => true]);
    Category::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/categories')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($active->id);
});

it('does not cap prices when no price range is sent', function () {
    $expensive = Product::factory()->create(['is_active' => true, 'price' => 4500]);

    $response = $this->getJson('/api/products')->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($expensive->id);
});

it('applies a lone price_from without an upper bound', function () {
    Product::factory()->create(['is_active' => true, 'price' => 20]);
    $expensive = Product::factory()->create(['is_active' => true, 'price' => 4500]);

    $response = $this->getJson('/api/products?price_from=100')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($expensive->id);
});

it('sorts products by price in both directions', function (string $sort, array $expected) {
    $prices = [50, 10, 30];
    foreach ($prices as $price) {
        Product::factory()->create(['is_active' => true, 'price' => $price]);
    }

    $response = $this->getJson("/api/products?sort={$sort}")->assertOk();

    expect(collect($response->json('data'))->pluck('price')->all())->toEqual($expected);
})->with([
    'low to high' => ['price_asc', [10.0, 30.0, 50.0]],
    'high to low' => ['price_desc', [50.0, 30.0, 10.0]],
]);

it('embeds the first page of published reviews, newest first, in the product detail', function () {
    $product = Product::factory()->create(['is_active' => true]);
    $older = Review::factory()->create(['product_id' => $product->id, 'status' => ReviewStatusEnum::PUBLISHED->value, 'created_at' => now()->subDay()]);
    $newer = Review::factory()->create(['product_id' => $product->id, 'status' => ReviewStatusEnum::PUBLISHED->value, 'created_at' => now()]);
    Review::factory()->create(['product_id' => $product->id, 'status' => ReviewStatusEnum::HIDDEN->value]);

    $response = $this->getJson("/api/products/{$product->slug}")
        ->assertOk()
        ->assertJsonStructure(['data', 'reviews' => ['data', 'links', 'meta']])
        ->assertJsonPath('reviews.meta.total', 2);

    expect(collect($response->json('reviews.data'))->pluck('id')->all())->toBe([$newer->id, $older->id]);
});
