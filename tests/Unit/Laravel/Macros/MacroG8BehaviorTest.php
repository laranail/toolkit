<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Behaviour of the Collection macros folded in by batch G8a.
 */
class MacroG8BehaviorTest extends TestCase
{
    public function test_map_key_value_pairs_rebuilds_an_associative_collection(): void
    {
        $pairs = collect([
            ['key' => 'a', 'value' => 1],
            ['key' => 'b', 'value' => 2],
        ]);

        $this->assertSame(['a' => 1, 'b' => 2], $pairs->mapKeyValuePairs()->all());
    }

    public function test_map_key_value_pairs_skips_rows_without_a_key(): void
    {
        $pairs = collect([
            ['key' => 'a', 'value' => 1],
            ['value' => 2],
        ]);

        $this->assertSame(['a' => 1], $pairs->mapKeyValuePairs()->all());
    }

    public function test_sort_search_results_orders_by_relevance(): void
    {
        $items = collect([
            ['title' => 'laravel toolkit guide'],
            ['title' => 'laravel'],
            ['title' => 'unrelated'],
            ['title' => 'a laravel mention'],
        ]);

        $sorted = $items->sortSearchResults('laravel', 'title')->all();

        // Exact match ranks first; the unrelated row ranks last.
        $this->assertSame('laravel', $sorted[0]['title']);
        $this->assertSame('unrelated', $sorted[array_key_last($sorted)]['title']);
    }

    public function test_sort_search_results_reindexes(): void
    {
        $sorted = collect([['t' => 'b'], ['t' => 'a']])->sortSearchResults('a', 't');

        $this->assertSame([0, 1], $sorted->keys()->all());
    }
}
