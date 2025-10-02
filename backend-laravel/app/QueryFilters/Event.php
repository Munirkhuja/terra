<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class Event
{
    public function handle(Builder $query, $next)
    {
        if (request()->filled('event')) {
            $query = $query->where('event', request('event'));
        }

        return $next($query);
    }
}
