<?php

namespace App\Http\Controllers;

use App\Data\CategoryData;
use App\Repositories\CategoryRepository;
use Spatie\LaravelData\DataCollection;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    public function index(): DataCollection
    {
        return CategoryData::collect($this->categoryRepository->getActiveCategories(), DataCollection::class)->wrap('data');
    }
}
