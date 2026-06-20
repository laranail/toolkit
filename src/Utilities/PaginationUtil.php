<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Utilities;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PaginationUtil
{
    /**
     * Paginate a collection.
     *
     * @return LengthAwarePaginator
     */
    public static function paginate(array $items, int $perPage, int $currentPage, array $options = [])
    {
        $paginator = new LengthAwarePaginator(
            array_slice($items, ($currentPage - 1) * $perPage, $perPage),
            count($items),
            $perPage,
            $currentPage,
            $options
        );

        return $paginator;
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
        $page = $page ?: (request()->input('page', 1));

        return $query->paginate($perPage, ['*'], 'page', $page)->appends($options);
    }
}
