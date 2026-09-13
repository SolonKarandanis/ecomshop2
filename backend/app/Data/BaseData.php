<?php

namespace App\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

abstract class BaseData extends Data
{
    protected function calculateResponseStatus(Request $request): int
    {
        return 200;
    }
}
