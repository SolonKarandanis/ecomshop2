<?php

namespace App\Http\Controllers;

use App\Data\ApplicationConfigData;

class ConfigController extends Controller
{
    public function show(): ApplicationConfigData
    {
        return (new ApplicationConfigData(
            suppliers_enabled: (bool) config('features.suppliers_enabled'),
        ))->wrap('data');
    }
}
