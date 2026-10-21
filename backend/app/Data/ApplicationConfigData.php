<?php

namespace App\Data;

/**
 * Public feature flags the frontend needs before anyone logs in.
 */
class ApplicationConfigData extends BaseData
{
    public function __construct(
        public bool $suppliers_enabled,
    ) {}
}
