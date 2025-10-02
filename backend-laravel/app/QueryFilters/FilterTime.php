<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

trait FilterTime
{
    public function filterTime(Builder $query, $key)
    {
        $equalHas = request()->filled($key);
        $fromHas = request()->filled("{$key}_from");
        $toHas = request()->filled("{$key}_to");
        if ($equalHas) {
            return $query->whereTime($key, request($key));
        }
        if ($fromHas && $toHas) {
            return $query->whereTime($key, '>=', request("{$key}_from"))
                ->whereTime($key, '<=', request("{$key}_to"));
        }
        if ($fromHas && !$toHas) {
            return $query->whereTime($key, '>=', request("{$key}_from"));
        }
        if (!$fromHas && $toHas) {
            return $query->whereTime($key, '<=', request("{$key}_to"));
        }

        return $query;
    }
}
