<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class Pagination
{
    /**
     * Paginate a collection.
     *
     * @return LengthAwarePaginator
     */
    public static function paginate(array $items, int $perPage, int $currentPage, array $options = [])
    {
        // Guard against zero/negative inputs (a negative offset would slice from
        // the end of the array and return the wrong page).
        $perPage = max(1, $perPage);
        $currentPage = max(1, $currentPage);

        return new LengthAwarePaginator(
            array_slice($items, ($currentPage - 1) * $perPage, $perPage),
            count($items),
            $perPage,
            $currentPage,
            $options
        );
    }

    /**
     * Paginate an Eloquent or Query Builder instance.
     *
     * @param Builder|\Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function paginateQuery($query, int $perPage, ?int $page = null, array $options = [])
    {
        $page ??= request()->integer('page', 1);

        return $query->paginate($perPage, ['*'], 'page', $page)->appends($options);
    }
}
