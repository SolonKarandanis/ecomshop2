<?php

namespace App\Http\Controllers;

use App\Data\AttributeData;
use App\Repositories\AttributeRepository;
use Spatie\LaravelData\DataCollection;

class AttributeController extends Controller
{
    public function __construct(
        private readonly AttributeRepository $attributeRepository,
    ) {}

    public function index(): DataCollection
    {
        return AttributeData::collect($this->attributeRepository->getAttributesWithOptions(), DataCollection::class)->wrap('data');
    }
}
