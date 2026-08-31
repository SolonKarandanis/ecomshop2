<?php

namespace App\Search;

use App\Dtos\ProductSearchFilterDto;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductSearchEngineInterface
{
    public function search(ProductSearchFilterDto $dto): LengthAwarePaginator;
}
