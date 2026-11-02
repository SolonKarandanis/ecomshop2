<?php

use App\Enums\ReviewStatusEnum;
use App\Enums\RolesEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

// The busiest lists must cost the same number of queries for a page of 3 rows
// as for a page of 30: a count that grows with the rows is an N+1. Lazy
// loading already throws in tests (AppServiceProvider); this also catches
// queries run on purpose per row, like `$order->items()->count()` in a loop.

/**
 * The request is sent twice and only the second is counted: the first also
 * fills per-process caches (Spatie's permissions, for one) that a real
 * request usually finds already warm.
 *
 * @return array{0: TestResponse, 1: int} the response and how many queries it ran
 */
function countQueries(callable $request): array
{
    $request();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [$response, $count];
}

function queryBudgetUser(RolesEnum $role): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('lists products in a constant number of queries, however many there are', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $brand = Brand::factory()->create(['is_active' => true]);
    $supplier = queryBudgetUser(RolesEnum::ROLE_SUPPLIER);
    $make = fn (int $count) => Product::factory()->count($count)->create([
        'is_active' => true, 'category_id' => $category->id, 'brand_id' => $brand->id, 'supplier_id' => $supplier->id,
    ]);

    $make(3);
    [$few, $fewQueries] = countQueries(fn () => $this->getJson('/api/products?per_page=50'));
    $make(27);
    [$many, $manyQueries] = countQueries(fn () => $this->getJson('/api/products?per_page=50'));

    expect($few->assertOk()->json('data'))->toHaveCount(3)
        ->and($many->assertOk()->json('data'))->toHaveCount(30)
        ->and($manyQueries)->toBe($fewQueries);
});

it('lists a Buyer\'s orders in a constant number of queries, however many there are', function () {
    $buyer = queryBudgetUser(RolesEnum::ROLE_BUYER);
    $supplier = queryBudgetUser(RolesEnum::ROLE_SUPPLIER);
    $make = fn (int $count) => Order::factory()->count($count)->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id]);

    $make(3);
    [$few, $fewQueries] = countQueries(fn () => $this->actingAs($buyer)->getJson('/api/orders?perPage=50'));
    $make(27);
    [$many, $manyQueries] = countQueries(fn () => $this->actingAs($buyer)->getJson('/api/orders?perPage=50'));

    expect($few->assertOk()->json('data'))->toHaveCount(3)
        ->and($many->assertOk()->json('data'))->toHaveCount(30)
        ->and($manyQueries)->toBe($fewQueries);
});

it('lists a product\'s reviews in a constant number of queries, however many there are', function () {
    $product = Product::factory()->create(['is_active' => true]);
    $make = fn (int $count) => Review::factory()->count($count)->create([
        'product_id' => $product->id, 'status' => ReviewStatusEnum::PUBLISHED->value,
    ]);

    $make(3);
    [$few, $fewQueries] = countQueries(fn () => $this->getJson("/api/products/{$product->id}/reviews?per_page=50"));
    $make(27);
    [$many, $manyQueries] = countQueries(fn () => $this->getJson("/api/products/{$product->id}/reviews?per_page=50"));

    expect($few->assertOk()->json('data'))->toHaveCount(3)
        ->and($many->assertOk()->json('data'))->toHaveCount(30)
        ->and($manyQueries)->toBe($fewQueries);
});
