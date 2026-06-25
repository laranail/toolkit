<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Support\Collection;

class CollectionFilter
{
    /**
     * Filter a collection by a given key value pair.
     *
     * @param string $value
     *
     * @return Collection
     */
    public static function filter(Collection $items, string $name, string $operator, $value)
    {
        return $items->filter(function ($item) use ($name, $operator, $value) {
            $actual = data_get($item, $name);

            if ($operator === 'equals') {
                return $actual == $value;
            }

            if ($operator === 'not_equals') {
                return $actual != $value;
            }

            // String operators: compare case-insensitively, guarding non-scalars.
            $haystack = is_scalar($actual) ? strtolower((string) $actual) : '';
            $needle = strtolower((string) $value);

            return match ($operator) {
                'contains' => str_contains($haystack, $needle),
                'not_contains' => !str_contains($haystack, $needle),
                'starts_with' => str_starts_with($haystack, $needle),
                'ends_with' => str_ends_with($haystack, $needle),
                default => false,
            };
        });
    }
}
