<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class Title
{
    public function handle(Builder $query, $next)
    {
        if (request()->filled('title')) {
            $query->where('title', 'LIKE', '%' . request('title') . '%');
        }

        return $next($query);
    }
}
