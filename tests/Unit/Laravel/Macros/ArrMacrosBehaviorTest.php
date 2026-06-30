<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Support\Arr;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Exhaustive behaviour tests for the toolkit's Arr macros — exact input→output
 * assertions (incl. edge cases) so the mutation gate has no surviving operator/
 * return/boundary mutants in these pure array macros.
 */
final class ArrMacrosBehaviorTest extends TestCase
{
    public function test_filter_nulls_keeps_falsey_non_nulls(): void
    {
        $this->assertSame(
            ['a' => 0, 'b' => '', 'c' => false, 'e' => 1],
            Arr::filterNulls(['a' => 0, 'b' => '', 'c' => false, 'd' => null, 'e' => 1]),
        );
        $this->assertSame([], Arr::filterNulls([null, null]));
    }

    public function test_filter_empty_drops_only_empty_values(): void
    {
        $this->assertSame(
            [3 => 'x', 5 => 1],
            Arr::filterEmpty(['', 0, null, 'x', false, 1, []]),
        );
        $this->assertSame([], Arr::filterEmpty([]));
    }

    public function test_map_keys_rewrites_keys_via_callback(): void
    {
        $this->assertSame(
            ['A_1' => 1, 'B_2' => 2],
            Arr::mapKeys(['a' => 1, 'b' => 2], fn ($k, $v): string => strtoupper((string) $k) . '_' . $v),
        );
        $this->assertSame([], Arr::mapKeys([], fn ($k, $v) => $k));
    }

    public function test_insert_after_inserts_following_the_key(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2, 'x' => 9, 'c' => 3],
            Arr::insertAfter(['a' => 1, 'b' => 2, 'c' => 3], 'b', ['x' => 9]),
        );
    }

    public function test_insert_after_appends_when_key_missing(): void
    {
        $this->assertSame(
            ['a' => 1, 'x' => 9],
            Arr::insertAfter(['a' => 1], 'nope', ['x' => 9]),
        );
    }

    public function test_insert_before_inserts_preceding_the_key(): void
    {
        $this->assertSame(
            ['a' => 1, 'x' => 9, 'b' => 2],
            Arr::insertBefore(['a' => 1, 'b' => 2], 'b', ['x' => 9]),
        );
    }

    public function test_insert_before_prepends_when_key_missing(): void
    {
        $this->assertSame(
            ['x' => 9, 'a' => 1],
            Arr::insertBefore(['a' => 1], 'nope', ['x' => 9]),
        );
    }

    public function test_remove_value_drops_strictly_equal_and_reindexes(): void
    {
        $this->assertSame([1, 3], Arr::removeValue([1, 2, 3, 2], 2));
        // strict: '2' (string) is not removed when removing int 2
        $this->assertSame(['2'], Arr::removeValue(['2', 2], 2));
    }

    public function test_remove_values_drops_any_listed_value(): void
    {
        $this->assertSame([1, 4], Arr::removeValues([1, 2, 3, 4], [2, 3]));
        $this->assertSame([1, 2], Arr::removeValues([1, 2], []));
    }

    public function test_rename_key_preserves_order_and_noops_when_absent(): void
    {
        $this->assertSame(
            ['a' => 1, 'B' => 2, 'c' => 3],
            Arr::renameKey(['a' => 1, 'b' => 2, 'c' => 3], 'b', 'B'),
        );
        $this->assertSame(['a' => 1], Arr::renameKey(['a' => 1], 'missing', 'X'));
    }

    public function test_rename_keys_maps_many_and_skips_missing(): void
    {
        // Renamed keys are re-appended at the end in change order; untouched keys keep position.
        $this->assertSame(
            ['b' => 2, 'x' => 1, 'y' => 3],
            Arr::renameKeys(['a' => 1, 'b' => 2, 'c' => 3], ['a' => 'x', 'c' => 'y', 'z' => 'q']),
        );
    }

    public function test_average_handles_keys_non_numerics_and_empty(): void
    {
        $this->assertSame(2.5, Arr::average([1, 2, 3, 4]));
        $this->assertSame(0, Arr::average([]));
        $this->assertSame(0, Arr::average(['a', 'b']));
        $this->assertSame(10, Arr::average([['v' => 5], ['v' => 15]], 'v'));
    }

    public function test_median_odd_even_and_empty(): void
    {
        $this->assertSame(3, Arr::median([5, 1, 3]));          // odd → middle after sort
        $this->assertSame(2.5, Arr::median([1, 2, 3, 4]));     // even → mean of middles
        $this->assertSame(0, Arr::median([]));
        $this->assertSame(20, Arr::median([['v' => 10], ['v' => 30]], 'v'));
    }

    public function test_group_by_key_groups_and_skips_non_scalar_keys(): void
    {
        $this->assertSame(
            ['x' => [['t' => 'x', 'n' => 1], ['t' => 'x', 'n' => 3]], 'y' => [['t' => 'y', 'n' => 2]]],
            Arr::groupByKey([['t' => 'x', 'n' => 1], ['t' => 'y', 'n' => 2], ['t' => 'x', 'n' => 3]], 't'),
        );
        $this->assertSame([], Arr::groupByKey([['t' => ['nested']]], 't'));
    }

    public function test_unique_by_keeps_first_per_key(): void
    {
        $this->assertSame(
            [['id' => 1, 'v' => 'a'], ['id' => 2, 'v' => 'b']],
            Arr::uniqueBy([['id' => 1, 'v' => 'a'], ['id' => 1, 'v' => 'c'], ['id' => 2, 'v' => 'b']], 'id'),
        );
    }

    public function test_sort_by_keys_orders_by_given_keys_then_unknown_last(): void
    {
        $this->assertSame(
            ['b' => 2, 'a' => 1, 'z' => 26],
            Arr::sortByKeys(['a' => 1, 'z' => 26, 'b' => 2], ['b', 'a']),
        );
    }
}
