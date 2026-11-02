<?php

// Which way the layers may depend on each other. Each rule held when it was
// written; a failure here means a new shortcut, not an old one. Arch tests
// only see class references: they can't see a raw query into another
// area's table, which code review still has to catch.
//
//   Http / Filament  ->  Services  ->  Repositories  ->  Models
//                    Data and Dtos are carried between them

use App\Http\Controllers\AttributeController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PaymentMethodController;

arch('models know nothing about the layers that use them')
    ->expect('App\Models')
    ->not->toUse(['App\Services', 'App\Repositories', 'App\Http', 'App\Filament']);

arch('repositories only query, they never call back up into services or HTTP')
    ->expect('App\Repositories')
    ->not->toUse(['App\Services', 'App\Http', 'App\Filament']);

// The four controllers are plain catalogue lookups with nothing for a service
// to add; a pass-through service just to satisfy this rule would be ceremony.
arch('repositories are reached through services, apart from the plain catalogue lookups')
    ->expect('App\Repositories')
    ->toOnlyBeUsedIn([
        'App\Repositories',
        'App\Services',
        'App\Payments',
        'App\StateMachines',
        'App\Exports',
        AttributeController::class,
        BrandController::class,
        CategoryController::class,
        PaymentMethodController::class,
    ]);

arch('the admin panel goes through models and services, never repositories')
    ->expect('App\Filament')
    ->not->toUse('App\Repositories');

arch('services never depend on controllers or the admin panel')
    ->expect('App\Services')
    ->not->toUse(['App\Http\Controllers', 'App\Filament']);

// Dtos may read a FormRequest (their fromRequest() constructors), but neither
// they nor the response Data objects may run queries or business logic.
arch('Data and Dtos only carry values')
    ->expect(['App\Data', 'App\Dtos'])
    ->not->toUse(['App\Services', 'App\Repositories', 'App\Http\Controllers', 'App\Filament']);

arch('controllers leave queries to repositories')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
