<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Behaviour of the macros folded in by batch G6a.
 */
class MacroG6aBehaviorTest extends TestCase
{
    public function test_before_returns_previous_item(): void
    {
        $collection = collect(['a', 'b', 'c']);

        $this->assertSame('a', $collection->before('b'));
        $this->assertNull($collection->before('a'));
        $this->assertNull($collection->before('missing'));
    }

    public function test_insert_at_inserts_without_mutating(): void
    {
        $collection = collect(['a', 'b', 'd']);
        $result = $collection->insertAt(2, 'c');

        $this->assertSame(['a', 'b', 'c', 'd'], $result->all());
        // Original untouched.
        $this->assertSame(['a', 'b', 'd'], $collection->all());
    }

    public function test_rotate_handles_positive_and_negative_offsets(): void
    {
        $collection = collect([1, 2, 3, 4]);

        $this->assertSame([2, 3, 4, 1], $collection->rotate(1)->all());
        $this->assertSame([4, 1, 2, 3], $collection->rotate(-1)->all());
        $this->assertSame([], collect([])->rotate(2)->all());
    }

    public function test_first_or_push_returns_match_or_pushes_value(): void
    {
        $found = collect([1, 2, 3]);
        $this->assertSame(2, $found->firstOrPush(fn (int $n): bool => $n === 2, 99));
        $this->assertSame([1, 2, 3], $found->all());

        $missing = collect([1, 2, 3]);
        $this->assertSame(99, $missing->firstOrPush(fn (int $n): bool => $n === 5, fn (): int => 99));
        $this->assertSame([1, 2, 3, 99], $missing->all());
    }

    public function test_each_cons_returns_consecutive_windows(): void
    {
        $windows = collect([1, 2, 3, 4])->eachCons(2)->map->all()->all();

        $this->assertSame([[1, 2], [2, 3], [3, 4]], $windows);
        $this->assertSame([], collect([1])->eachCons(2)->all());
    }

    public function test_slice_before_and_chunk_by(): void
    {
        $sliced = collect([1, 2, 4, 6, 7])
            ->sliceBefore(fn (int $item, int $prev): bool => $item % 2 !== $prev % 2)
            ->map->all()
            ->all();
        $this->assertSame([[1], [2, 4, 6], [7]], $sliced);

        $chunked = collect([1, 1, 2, 2, 3])
            ->chunkBy(fn (int $item): int => $item)
            ->map->all()
            ->all();
        $this->assertSame([[1, 1], [2, 2], [3]], $chunked);
    }

    public function test_group_by_model(): void
    {
        $a = new MacroG6aFakeModel(['id' => 1]);
        $b = new MacroG6aFakeModel(['id' => 2]);

        $rows = collect([
            ['model' => $a, 'n' => 'x'],
            ['model' => $b, 'n' => 'y'],
            ['model' => $a, 'n' => 'z'],
        ])->groupByModel('model');

        $this->assertCount(2, $rows);
        $first = $rows->first();
        $this->assertSame($a, $first[0]);
        $this->assertCount(2, $first[1]);
    }

    public function test_for_select_box(): void
    {
        $items = collect([
            ['id' => 1, 'name' => 'Banana'],
            ['id' => 2, 'name' => 'apple'],
        ]);

        $withEmpty = $items->forSelectBox('id', 'name');
        $this->assertSame(['' => '', 2 => 'apple', 1 => 'Banana'], $withEmpty);

        $withoutEmpty = $items->forSelectBox('id', 'name', false);
        $this->assertSame([2 => 'apple', 1 => 'Banana'], $withoutEmpty);
    }

    public function test_extract_returns_unkeyed_values_with_nulls(): void
    {
        $result = collect(['a' => 1, 'b' => 2])->extract(['a', 'missing', 'b']);

        $this->assertSame([1, null, 2], $result->all());
        [$a, $missing, $b] = $result->all();
        $this->assertSame(1, $a);
        $this->assertNull($missing);
        $this->assertSame(2, $b);
    }

    public function test_tail_to_pairs_from_pairs(): void
    {
        $this->assertSame(['b', 'c'], collect(['a', 'b', 'c'])->tail()->all());

        $pairs = collect(['x' => 1, 'y' => 2])->toPairs();
        $this->assertSame([['x', 1], ['y', 2]], $pairs->all());

        $assoc = collect([['x', 1], ['y', 2]])->fromPairs();
        $this->assertSame(['x' => 1, 'y' => 2], $assoc->all());
    }

    public function test_if_empty_runs_only_when_empty(): void
    {
        $ran = false;
        collect([])->ifEmpty(function () use (&$ran): void {
            $ran = true;
        });
        $this->assertTrue($ran);

        $ran = false;
        collect([1])->ifEmpty(function () use (&$ran): void {
            $ran = true;
        });
        $this->assertFalse($ran);
    }

    public function test_arr_rename_keys_renames_many_and_skips_missing(): void
    {
        $result = Arr::renameKeys(
            ['first' => 'a', 'second' => 'b', 'keep' => 'c'],
            ['first' => 'one', 'second' => 'two', 'absent' => 'nope'],
        );

        $this->assertSame(['keep' => 'c', 'one' => 'a', 'two' => 'b'], $result);
    }

    public function test_str_reading_minutes(): void
    {
        $words = implode(' ', array_fill(0, 400, 'word'));

        $this->assertSame(2, Str::readingMinutes($words));
        $this->assertSame(1, Str::readingMinutes('short text'));
        $this->assertSame(2, str($words)->readingMinutes());
    }

    public function test_str_highlight_words(): void
    {
        $html = Str::highlightWords('the quick brown fox', 'quick');

        $this->assertInstanceOf(HtmlString::class, $html);
        $this->assertSame('the <mark>quick</mark> brown fox', (string) $html);

        // Multiple terms, case-insensitive.
        $multi = (string) Str::highlightWords('Foo and bar', ['foo', 'BAR']);
        $this->assertSame('<mark>Foo</mark> and <mark>bar</mark>', $multi);
    }
}

/**
 * Minimal model stub for the groupByModel macro test.
 */
class MacroG6aFakeModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
