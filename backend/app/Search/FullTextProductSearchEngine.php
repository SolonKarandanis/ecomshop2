<?php

namespace App\Search;

use Illuminate\Database\Eloquent\Builder;

//whereFullText() is Laravel's query builder method for MySQL's MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE) — it's a single call
//across both columns (no manual orWhere, MySQL's engine handles matching either column internally), and it requires the FULLTEXT index
//the migration created; without that index this call errors.

//Keep the same mb_strlen($term) >= 3 guard wrapping it in when() — and it's worth being precise about why, since it's not there
//to prevent an error. Per the acceptance criteria, MySQL doesn't error on a 1-2 char term; MATCH() AGAINST() on a too-short term
//just silently returns zero rows, because InnoDB never indexed a token that short (innodb_ft_min_token_size defaults to 3).
//Without the when() guard, a short search term would AND a zero-match clause onto the query and wipe out the results entirely — but the spec
//from #14 says a short term should be ignored, not turn the results empty (other filters should still apply normally).
//So the guard is what makes "search too short → ignored" true for this engine too, not a safety net.

class FullTextProductSearchEngine extends BaseProductSearchEngine
{
    protected function applySearchTerm(Builder $query, string $term): void
    {
        $query->whereFullText(['name', 'description'], $term);
    }
}
