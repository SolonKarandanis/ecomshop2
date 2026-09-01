<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Repositories\CategoryRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->categoryRepository->getActiveCategories());
    }
}
