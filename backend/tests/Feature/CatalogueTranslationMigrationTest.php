<?php

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// SQLite only: schema changes there roll back with RefreshDatabase's
// transaction, while on MariaDB each one commits and would leak into the next
// test.
it('moves product names to Greek and category names to English, and down() puts them back', function () {
    $migration = require database_path('migrations/2026_11_01_120000_move_catalogue_names_to_translation_tables.php');
    $migration->down();

    $categoryId = DB::table('categories')->insertGetId(['name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);
    $productId = DB::table('products')->insertGetId([
        'name' => 'Samsung Smart Τηλεόραση 85"',
        'description' => 'Ελληνική περιγραφή',
        'slug' => 'samsung-smart-tv',
        'price' => 1299,
        'category_id' => $categoryId,
        'brand_id' => Brand::factory()->create()->id,
        'supplier_id' => User::factory()->create()->id,
    ]);

    $migration->up();

    expect(DB::table('product_translations')->where('product_id', $productId)->get(['locale', 'name', 'description'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['locale' => 'el', 'name' => 'Samsung Smart Τηλεόραση 85"', 'description' => 'Ελληνική περιγραφή']])
        ->and(DB::table('category_translations')->where('category_id', $categoryId)->get(['locale', 'name'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['locale' => 'en', 'name' => 'Laptops']]);

    $migration->down();

    expect(DB::table('products')->where('id', $productId)->first(['name', 'description']))
        ->toEqual((object) ['name' => 'Samsung Smart Τηλεόραση 85"', 'description' => 'Ελληνική περιγραφή'])
        ->and(DB::table('categories')->where('id', $categoryId)->value('name'))->toBe('Laptops');

    $migration->up();
})->skip(fn () => DB::connection()->getDriverName() !== 'sqlite', 'Schema changes would commit RefreshDatabase\'s transaction outside SQLite.');
