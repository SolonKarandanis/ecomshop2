<?php

namespace App\Repositories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentMethodRepository
{
    public function modelQuery(): Builder| PaymentMethod{
        return PaymentMethod::query();
    }

    public function findByResourceKey(string $resourceKey): PaymentMethod{
        return $this->modelQuery()->whereResourceKey($resourceKey)->first();
    }

    public function findAll(): Collection
    {
        return $this->modelQuery()->get();
    }
}
