<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class CursorPaginateLoc
{
    public function handle(Builder $query, $next)
    {
        if (request()->filled('limit') && request('limit') < config('app.max_limit')) {
            $limit = request('limit');
        } else {
            $limit = config('app.default_limit');
        }

        return $next($query->cursorPaginate($limit)->withQueryString());
    }
}
