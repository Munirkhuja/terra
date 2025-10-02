<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class Description
{
    public function handle(Builder $query, $next)
    {
        if (request()->filled('description')) {
            $query->where('description', 'LIKE', '%' . request('description') . '%');
        }

        return $next($query);
    }
}
