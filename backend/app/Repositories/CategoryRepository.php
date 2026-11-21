<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryRepository
{
    /**
     * @return Builder<Category>
     */
    public function modelQuery(): Builder
    {
        return Category::query();
    }

    public function getActiveCategories(): Collection
    {
        return $this->modelQuery()->with('media')->active()->get();
    }
}
