<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class EqualKeyID
{
    use FilterNumber;

    public function handle(Builder $query, $next)
    {
        $query = $this->filterNumber($query, 'id');

        return $next($query);
    }
}
