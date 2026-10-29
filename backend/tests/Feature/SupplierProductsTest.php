<?php

use App\Enums\PaymentMethodEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Models\Attribute;
use App\Models\AttributeOptions;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

function supplierProductsUser(RolesEnum $role, UserStatusEnum $status = UserStatusEnum::ACTIVE): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create(['status' => $status->value, 'password' => Hash::make('password')]);
    $user->assignRole($role->value);

    return $user;
}

function supplierProductsPayload(array $overrides = []): array
{
    return [
        'name' => 'Aurora Laptop 14',
        'description' => 'A light laptop.',
        'price' => 899.5,
        'category_id' => Category::factory()->create(['is_active' => true])->id,
        'brand_id' => Brand::factory()->create(['is_active' => true])->id,
        'is_active' => true,
        'in_stock' => true,
        'on_sale' => false,
        ...$overrides,
    ];
}

/**
 * @return array{0: Attribute, 1: AttributeOptions, 2: AttributeOptions}
 */
function supplierProductsColorAttribute(): array
{
    $color = Attribute::create(['name' => 'attribute.color', 'type' => 'Image']);

    return [
        $color,
        AttributeOptions::create(['attribute_id' => $color->id, 'option_name' => 'attribute.option.color.silver']),
        AttributeOptions::create(['attribute_id' => $color->id, 'option_name' => 'attribute.option.color.midnight']),
    ];
}

function supplierProductsNextRequest(?TestResponse $previous = null): TestCase
{
    $client = test()->withCredentials()->withHeader('Origin', 'http://localhost:3000');

    if ($previous !== null) {
        foreach ($previous->headers->getCookies() as $cookie) {
            $client->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
    }

    forgetRequestState();
    app('auth')->forgetGuards();
    // An earlier auth:sanctum request leaves `sanctum` as the default guard, which can't log in.
    app('auth')->shouldUse('web');

    return $client;
}

beforeEach(function () {
    config(['features.suppliers_enabled' => true]);
    Storage::fake('media');
});

it("lists only the Supplier's own Products, including inactive ones", function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    Product::factory()->create(['supplier_id' => $supplier->id, 'is_active' => true]);
    Product::factory()->create(['supplier_id' => $supplier->id, 'is_active' => false]);
    Product::factory()->create(['supplier_id' => supplierProductsUser(RolesEnum::ROLE_SUPPLIER)->id]);

    $response = $this->actingAs($supplier)->getJson('/api/supplier-products')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('is_active')->sort()->values()->all())->toBe([false, true]);
    expect($response->json('meta.total'))->toBe(2);
});

it('shows a Product with its attribute values and images for editing', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    [$color, $silver] = supplierProductsColorAttribute();
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);
    $product->productAttributeValues()->create(['attribute_id' => $color->id, 'attribute_option_id' => $silver->id]);

    $this->actingAs($supplier)->getJson("/api/supplier-products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.category_id', $product->category_id)
        ->assertJsonPath('data.attributes.0.attribute_id', $color->id)
        ->assertJsonPath('data.attributes.0.attribute_option_id', $silver->id)
        ->assertJsonPath('data.images', []);
});

it('creates a Product owned by the Supplier, with a slug generated from its name', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    [$color, $silver, $midnight] = supplierProductsColorAttribute();

    $response = $this->actingAs($supplier)->postJson('/api/supplier-products', supplierProductsPayload([
        'attributes' => [
            ['attribute_id' => $color->id, 'attribute_option_id' => $silver->id],
            ['attribute_id' => $color->id, 'attribute_option_id' => $midnight->id],
        ],
    ]))->assertCreated();

    $response->assertJsonPath('data.slug', 'aurora-laptop-14')
        ->assertJsonPath('data.is_featured', false)
        ->assertJsonCount(2, 'data.attributes');

    $product = Product::findOrFail($response->json('data.id'));
    expect($product->supplier_id)->toBe($supplier->id);
    expect((float) $product->price)->toBe(899.5);
});

it('makes a generated slug unique', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    Product::factory()->create(['slug' => 'aurora-laptop-14']);

    $this->actingAs($supplier)->postJson('/api/supplier-products', supplierProductsPayload())
        ->assertCreated()
        ->assertJsonPath('data.slug', 'aurora-laptop-14-2');
});

it('rejects a slug that is already taken with a 422', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    Product::factory()->create(['slug' => 'taken']);

    $this->actingAs($supplier)->postJson('/api/supplier-products', supplierProductsPayload(['slug' => 'taken']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('ignores supplier_id and is_featured sent by the Supplier', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    $otherSupplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);

    $response = $this->actingAs($supplier)->postJson('/api/supplier-products', supplierProductsPayload([
        'supplier_id' => $otherSupplier->id,
        'is_featured' => true,
    ]))->assertCreated();

    $product = Product::findOrFail($response->json('data.id'));
    expect($product->supplier_id)->toBe($supplier->id);
    expect((bool) $product->is_featured)->toBeFalse();

    $this->actingAs($supplier)->patchJson("/api/supplier-products/{$product->id}", [
        'supplier_id' => $otherSupplier->id,
        'is_featured' => true,
    ])->assertOk();

    $product->refresh();
    expect($product->supplier_id)->toBe($supplier->id);
    expect((bool) $product->is_featured)->toBeFalse();
});

it('returns 422 per field for invalid Product data', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);

    $this->actingAs($supplier)->postJson('/api/supplier-products', [
        'price' => -1,
        'category_id' => 999,
        'brand_id' => 999,
        'attributes' => [['attribute_id' => 999]],
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'name', 'price', 'category_id', 'brand_id',
        'attributes.0.attribute_id', 'attributes.0.attribute_option_id',
    ]);
});

it('rejects an attribute option that belongs to a different attribute, or is repeated', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    [$color, $silver] = supplierProductsColorAttribute();
    $ram = Attribute::create(['name' => 'attribute.ram', 'type' => 'Select']);

    $this->actingAs($supplier)->postJson('/api/supplier-products', supplierProductsPayload([
        'attributes' => [
            ['attribute_id' => $ram->id, 'attribute_option_id' => $silver->id],
            ['attribute_id' => $color->id, 'attribute_option_id' => $silver->id],
            ['attribute_id' => $color->id, 'attribute_option_id' => $silver->id],
        ],
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'attributes.0.attribute_option_id',
        'attributes.2.attribute_option_id',
    ])->assertJsonMissingValidationErrors(['attributes.1.attribute_option_id']);
});

it('updates only the fields sent, keeping attribute values unless they are sent', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    [$color, $silver, $midnight] = supplierProductsColorAttribute();
    $product = Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Old', 'slug' => 'old', 'price' => 10]);
    $kept = $product->productAttributeValues()->create(['attribute_id' => $color->id, 'attribute_option_id' => $silver->id]);

    $this->actingAs($supplier)->patchJson("/api/supplier-products/{$product->id}", ['price' => 20])
        ->assertOk()
        ->assertJsonPath('data.price', 20)
        ->assertJsonPath('data.name', 'Old')
        ->assertJsonPath('data.slug', 'old')
        ->assertJsonCount(1, 'data.attributes');

    $this->actingAs($supplier)->patchJson("/api/supplier-products/{$product->id}", [
        'attributes' => [
            ['attribute_id' => $color->id, 'attribute_option_id' => $silver->id],
            ['attribute_id' => $color->id, 'attribute_option_id' => $midnight->id],
        ],
    ])->assertOk()->assertJsonCount(2, 'data.attributes');

    // The unchanged pair keeps its row (and so any attribute images on it).
    expect($product->productAttributeValues()->pluck('id'))->toContain($kept->id);

    $this->actingAs($supplier)->patchJson("/api/supplier-products/{$product->id}", ['attributes' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data.attributes');
});

it("lets a Product keep its own slug on update but not take another Product's", function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    $product = Product::factory()->create(['supplier_id' => $supplier->id, 'slug' => 'mine']);
    Product::factory()->create(['slug' => 'theirs']);

    $this->actingAs($supplier)->patchJson("/api/supplier-products/{$product->id}", ['slug' => 'mine'])->assertOk();
    $this->actingAs($supplier)->patchJson("/api/supplier-products/{$product->id}", ['slug' => 'theirs'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('deletes a Product that has never been ordered, with its attribute values', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    [$color, $silver] = supplierProductsColorAttribute();
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);
    $product->productAttributeValues()->create(['attribute_id' => $color->id, 'attribute_option_id' => $silver->id]);

    $this->actingAs($supplier)->deleteJson("/api/supplier-products/{$product->id}")->assertNoContent();

    $this->assertModelMissing($product);
    $this->assertDatabaseCount('product_attribute_values', 0);
});

it('refuses to delete a Product that appears in an Order, leaving the Order intact', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    $buyer = supplierProductsUser(RolesEnum::ROLE_BUYER);
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);
    $order = Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id]);
    $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_amount' => 10, 'total_amount' => 10]);

    $this->actingAs($supplier)->deleteJson("/api/supplier-products/{$product->id}")
        ->assertStatus(409)
        ->assertJsonPath('error', 'ProductException');

    $this->assertModelExists($product);
    expect($order->items()->count())->toBe(1);
});

it('uploads and removes Product images', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);

    $response = $this->actingAs($supplier)->post("/api/supplier-products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('front.jpg', 800, 600), UploadedFile::fake()->image('back.png', 800, 600)],
    ])->assertOk()->assertJsonCount(2, 'data.images');

    expect($product->getMedia(Product::IMAGES_COLLECTION))->toHaveCount(2);
    expect($response->json('data.images.0.thumbnail'))->toContain('thumb');

    $mediaId = $response->json('data.images.0.id');
    $this->actingAs($supplier)->deleteJson("/api/supplier-products/{$product->id}/images/{$mediaId}")
        ->assertOk()
        ->assertJsonCount(1, 'data.images');

    expect($product->fresh()->getMedia(Product::IMAGES_COLLECTION))->toHaveCount(1);
});

it('rejects non-image uploads and more than the maximum number of images', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);

    $this->actingAs($supplier)->post("/api/supplier-products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
    ])->assertUnprocessable()->assertJsonValidationErrors(['images.0']);

    $this->actingAs($supplier)->post("/api/supplier-products/{$product->id}/images", [
        'images' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, Product::MAX_IMAGES + 1)),
    ])->assertUnprocessable()->assertJsonValidationErrors(['images']);

    expect($product->getMedia(Product::IMAGES_COLLECTION))->toHaveCount(0);
});

it("returns 404 for an image that isn't one of the Product's", function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);
    $other = Product::factory()->create(['supplier_id' => $supplier->id]);
    $media = $other->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection(Product::IMAGES_COLLECTION, 'media');

    $this->actingAs($supplier)->deleteJson("/api/supplier-products/{$product->id}/images/{$media->id}")->assertNotFound();
    $this->assertModelExists($media);
});

it("rejects every action on another Supplier's Product with 403", function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    $product = Product::factory()->create(['supplier_id' => supplierProductsUser(RolesEnum::ROLE_SUPPLIER)->id]);

    $this->actingAs($supplier)->getJson("/api/supplier-products/{$product->id}")->assertForbidden();
    $this->actingAs($supplier)->patchJson("/api/supplier-products/{$product->id}", ['price' => 1])->assertForbidden();
    $this->actingAs($supplier)->deleteJson("/api/supplier-products/{$product->id}")->assertForbidden();
    $this->actingAs($supplier)->post("/api/supplier-products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('x.jpg')],
    ])->assertForbidden();

    $this->assertModelExists($product);
});

it('returns 404 for a Product that does not exist', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);

    $this->actingAs($supplier)->getJson('/api/supplier-products/999')->assertNotFound();
});

dataset('non-suppliers', [
    'a Buyer' => fn () => supplierProductsUser(RolesEnum::ROLE_BUYER),
    'an Admin' => fn () => supplierProductsUser(RolesEnum::ROLE_ADMIN),
    'a pending Supplier' => fn () => supplierProductsUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::PENDING),
    'an inactive Supplier' => fn () => supplierProductsUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::INACTIVE),
]);

it('rejects users who are not approved Suppliers with 403', function (User $user) {
    $product = Product::factory()->create(['supplier_id' => $user->id]);

    $this->actingAs($user)->getJson('/api/supplier-products')->assertForbidden();
    $this->actingAs($user)->getJson("/api/supplier-products/{$product->id}")->assertForbidden();
    $this->actingAs($user)->postJson('/api/supplier-products', supplierProductsPayload())->assertForbidden();
    $this->actingAs($user)->postJson('/api/supplier-products', [])->assertForbidden();
    $this->actingAs($user)->patchJson("/api/supplier-products/{$product->id}", ['price' => 1])->assertForbidden();
    $this->actingAs($user)->deleteJson("/api/supplier-products/{$product->id}")->assertForbidden();
})->with('non-suppliers');

it('rejects a Supplier with 403 when the Suppliers Feature is off', function () {
    config(['features.suppliers_enabled' => false]);
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);

    $this->actingAs($supplier)->getJson('/api/supplier-products')->assertForbidden();
    $this->actingAs($supplier)->postJson('/api/supplier-products', supplierProductsPayload())->assertForbidden();
});

it('rejects a guest with 401', function () {
    $product = Product::factory()->create();

    $this->getJson('/api/supplier-products')->assertUnauthorized();
    $this->postJson('/api/supplier-products', supplierProductsPayload())->assertUnauthorized();
    $this->patchJson("/api/supplier-products/{$product->id}", ['price' => 1])->assertUnauthorized();
    $this->deleteJson("/api/supplier-products/{$product->id}")->assertUnauthorized();
});

it('puts an active Product in the public catalogue, and a Buyer can order it from that Supplier', function () {
    $supplier = supplierProductsUser(RolesEnum::ROLE_SUPPLIER);
    // Before any request: auth:sanctum switches the default guard, which new roles would pick up.
    $buyer = supplierProductsUser(RolesEnum::ROLE_BUYER);
    $created = $this->actingAs($supplier)
        ->postJson('/api/supplier-products', supplierProductsPayload(['is_active' => false]))
        ->assertCreated();
    $productId = $created->json('data.id');

    app('auth')->forgetGuards();
    $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($supplier)->patchJson("/api/supplier-products/{$productId}", ['is_active' => true])->assertOk();
    app('auth')->forgetGuards();
    $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.id', $productId);

    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    $login = supplierProductsNextRequest()
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();
    $add = supplierProductsNextRequest($login)
        ->postJson('/api/cart/items', ['product_id' => $productId, 'quantity' => 1])
        ->assertOk();
    supplierProductsNextRequest($add)->postJson('/api/checkout', [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'phone' => '555-1234',
        'address' => '1 Main St',
        'city' => 'Athens',
        'country' => 'Greece',
        'zipCode' => '12345',
        'paymentMethod' => PaymentMethodEnum::CASH_ON_DELIVERY->value,
    ])->assertOk();

    $order = Order::where('user_id', $buyer->id)->sole();
    expect($order->supplier_id)->toBe($supplier->id);
    expect($order->items()->value('product_id'))->toBe($productId);
});

it('lists active brands and attributes with their options for the product form', function () {
    Brand::factory()->create(['is_active' => true, 'name' => 'Acme']);
    Brand::factory()->create(['is_active' => false]);
    [$color, $silver, $midnight] = supplierProductsColorAttribute();

    $this->getJson('/api/brands')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Acme');

    $this->getJson('/api/attributes')->assertOk()
        ->assertJsonPath('data.0.id', $color->id)
        ->assertJsonPath('data.0.name', 'attribute.color')
        ->assertJsonPath('data.0.label', 'Color')
        ->assertJsonPath('data.0.options.0.id', $silver->id)
        ->assertJsonPath('data.0.options.0.label', 'Silver')
        ->assertJsonPath('data.0.options.1.id', $midnight->id);
});
