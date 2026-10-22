<?php

namespace App\Repositories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AttributeRepository
{
    public function modelQuery(): Builder|Attribute
    {
        return Attribute::query();
    }

    public function getAttributesWithOptions(): Collection
    {
        return $this->modelQuery()
            ->with(['attributeOptions' => fn ($query) => $query->whereNotNull('option_name')->orderBy('id')])
            ->whereNotNull('name')
            ->orderBy('id')
            ->get();
    }
}
