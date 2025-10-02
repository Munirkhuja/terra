<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class CreatedAt
{
    use FilterDateAt;

    public function handle(Builder $query, $next)
    {
        $query = $this->filterDateAt($query, 'created_at');

        return $next($query);
    }
}
