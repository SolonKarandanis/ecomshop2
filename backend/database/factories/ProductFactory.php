<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'slug' => $this->faker->unique()->slug,
            'description' => $this->faker->paragraph,
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'is_active' => $this->faker->boolean,
            'is_featured' => $this->faker->boolean,
            'in_stock' => $this->faker->boolean,
            'on_sale' => $this->faker->boolean,
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'supplier_id' => User::factory(),
        ];
    }
}
