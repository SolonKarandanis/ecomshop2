<?php

namespace App\Data;

/**
 * Public feature flags and limits the frontend needs before anyone logs in, so
 * it can check input the same way the API will.
 */
class ApplicationConfigData extends BaseData
{
    public function __construct(
        public bool $suppliers_enabled,
        public int $max_product_images,
        public int $max_product_image_kb,
    ) {}
}
