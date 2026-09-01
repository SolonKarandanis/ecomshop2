<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

it('lists active products as a paginated resource collection', function () {
    Product::factory()->count(3)->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $response = $this->getJson('/products')->assertOk();

    expect($response->json('data'))->toHaveCount(3);
    $response->assertJsonStructure(['data', 'links', 'meta']);
});

it('filters products by category', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $matching = Product::factory()->create(['is_active' => true, 'category_id' => $categoryA->id]);
    Product::factory()->create(['is_active' => true, 'category_id' => $categoryB->id]);

    $response = $this->getJson('/products?categories[]='.$categoryA->id)->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

it('filters products by price range', function () {
    $cheap = Product::factory()->create(['is_active' => true, 'price' => 20]);
    Product::factory()->create(['is_active' => true, 'price' => 500]);

    $response = $this->getJson('/products?price_from=0&price_to=100')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($cheap->id);
});

it('searches products by name', function () {
    $match = Product::factory()->create(['is_active' => true, 'name' => 'Wireless Keyboard']);
    Product::factory()->create(['is_active' => true, 'name' => 'Desk Lamp']);

    $response = $this->getJson('/products?q=keyboard')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($match->id);
});

it('rejects an invalid sort value', function () {
    $this->getJson('/products?sort=bogus')->assertUnprocessable()->assertJsonValidationErrors('sort');
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

    $this->getJson("/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.average_rating', 4.5)
        ->assertJsonPath('data.reviews_count', 12)
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.brand.id', $brand->id)
        ->assertJsonMissingPath('data.supplier_id');
});

it('returns 404 for an unknown product slug', function () {
    $this->getJson('/products/does-not-exist')->assertNotFound();
});

it('lists only active categories', function () {
    $active = Category::factory()->create(['is_active' => true]);
    Category::factory()->create(['is_active' => false]);

    $response = $this->getJson('/categories')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($active->id);
});
