<?php

namespace App\Http\Controllers;

use App\Data\BrandData;
use App\Repositories\BrandRepository;
use Spatie\LaravelData\DataCollection;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandRepository $brandRepository,
    ) {}

    public function index(): DataCollection
    {
        return BrandData::collect($this->brandRepository->getActiveBrands(), DataCollection::class)->wrap('data');
    }
}
