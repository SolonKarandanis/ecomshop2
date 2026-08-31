<?php

namespace App\Repositories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BrandRepository
{

    public function modelQuery(): Builder| Brand{
        return Brand::query();
    }

    public function getActiveBrands(): Collection
    {
        return $this->modelQuery()->with('media')->where('is_active','=',true)->get();
    }
}
