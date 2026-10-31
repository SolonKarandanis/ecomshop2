<?php

use App\Models\Attribute;
use App\Models\AttributeOptions;
use App\Models\Product;
use App\Models\ProductAttributeValues;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('media');
});

// A Product's catalogue picture is the image attached to its color attribute value.
it("returns a Product's color image as its thumbnail and large image", function () {
    $product = Product::factory()->create(['is_active' => true]);
    $color = Attribute::create(['name' => 'attribute.color', 'type' => 'Image']);
    $blue = AttributeOptions::create(['attribute_id' => $color->id, 'option_name' => 'Blue']);
    $value = ProductAttributeValues::create(['product_id' => $product->id, 'attribute_id' => $color->id, 'attribute_option_id' => $blue->id]);
    $value->addMedia(UploadedFile::fake()->image('blue.jpg', 800, 600))->toMediaCollection('product-attribute-images', 'media');

    $response = $this->getJson("/api/products/{$product->slug}")->assertOk();

    expect($response->json('data.thumbnail'))->toContain('thumb')
        ->and($response->json('data.image'))->toContain('large');
});

it('returns empty image URLs for a Product without a color image', function () {
    $product = Product::factory()->create(['is_active' => true]);

    $this->getJson("/api/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.thumbnail', '')
        ->assertJsonPath('data.image', '');
});
