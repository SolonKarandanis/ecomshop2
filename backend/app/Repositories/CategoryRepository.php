<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryRepository
{

    public function modelQuery(): Builder| Category{
        return Category::query();
    }

    public function getActiveCategories(): Collection
    {
        return $this->modelQuery()->with('media')->where('is_active','=',true)->get();
    }
}
