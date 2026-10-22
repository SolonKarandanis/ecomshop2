<?php

namespace App\Http\Controllers;

use App\Data\ApplicationConfigData;
use App\Models\Product;

class ConfigController extends Controller
{
    public function show(): ApplicationConfigData
    {
        return (new ApplicationConfigData(
            suppliers_enabled: (bool) config('features.suppliers_enabled'),
            max_product_images: Product::MAX_IMAGES,
            max_product_image_kb: Product::MAX_IMAGE_KB,
        ))->wrap('data');
    }
}
