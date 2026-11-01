<?php

namespace App\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class LikeProductSearchEngine extends BaseProductSearchEngine
{
    protected function applySearchTerm(Builder $query, string $term): void
    {
        $this->whereShownTextMatches($query, fn (QueryBuilder $translation) => $translation->where(fn (QueryBuilder $text) => $text
            ->where('t.name', 'like', "%{$term}%")
            ->orWhere('t.description', 'like', "%{$term}%")));
    }
}
