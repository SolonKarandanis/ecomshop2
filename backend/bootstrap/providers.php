<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ResponseServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ResponseServiceProvider::class,
];
