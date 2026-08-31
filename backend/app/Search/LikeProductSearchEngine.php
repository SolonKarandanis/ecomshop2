<?php

namespace App\Search;

use Illuminate\Database\Eloquent\Builder;

class LikeProductSearchEngine extends BaseProductSearchEngine
{
    protected function applySearchTerm(Builder $query, string $term): void
    {
        $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%"));
    }
}
