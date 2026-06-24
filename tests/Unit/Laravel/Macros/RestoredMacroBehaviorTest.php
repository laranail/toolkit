<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Behaviour of the macros restored from the legacy Laravel\Macros invokables
 * during the toolkit consolidation (Collection filter/lookup/insert macros and
 * the Str string-utility macros), including their edge/bug cases.
 */
class RestoredMacroBehaviorTest extends TestCase
{
    // ---- Collection::collectBy -------------------------------------------

    public function test_collect_by_wraps_value_at_key(): void
    {
        $collection = collect(['rows' => [1, 2, 3], 'name' => 'x']);

        $this->assertSame([1, 2, 3], $collection->collectBy('rows')->all());
        // A scalar value is wrapped as a single-item collection.
        $this->assertSame(['x'], $collection->collectBy('name')->all());
        // A missing key falls back to the (empty) default.
        $this->assertTrue($collection->collectBy('missing')->isEmpty());
    }

    // ---- Collection::filterMap -------------------------------------------

    public function test_filter_map_maps_then_drops_falsy(): void
    {
        $result = collect([1, 2, 3, 4])
            ->filterMap(fn (int $n): int|false => $n % 2 === 0 ? $n * 10 : false);

        $this->assertSame([1 => 20, 3 => 40], $result->all());
    }

    // ---- Collection::ifAny -----------------------------------------------

    public function test_if_any_runs_callback_only_when_not_empty(): void
    {
        $ran = false;
        collect([1])->ifAny(function () use (&$ran): void {
            $ran = true;
        });
        $this->assertTrue($ran);

        $ran = false;
        $returned = collect([])->ifAny(function () use (&$ran): void {
            $ran = true;
        });
        $this->assertFalse($ran);
        $this->assertInstanceOf(Collection::class, $returned);
    }

    // ---- Collection::none -------------------------------------------------

    public function test_none_is_the_inverse_of_contains(): void
    {
        $collection = collect([['id' => 1], ['id' => 2]]);

        $this->assertTrue(collect([1, 2, 3])->none(4));
        $this->assertFalse(collect([1, 2, 3])->none(2));
        // key + value form.
        $this->assertTrue($collection->none('id', 3));
        $this->assertFalse($collection->none('id', 1));
    }

    // ---- Collection::pluckToArray ----------------------------------------

    public function test_pluck_to_array_returns_plain_array(): void
    {
        $rows = collect([['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']]);

        $this->assertSame(['a', 'b'], $rows->pluckToArray('name'));
        $this->assertSame([1 => 'a', 2 => 'b'], $rows->pluckToArray('name', 'id'));
    }

    // ---- Collection::withSize --------------------------------------------

    public function test_with_size_builds_a_range_or_empty(): void
    {
        $this->assertSame([1, 2, 3], collect()->withSize(3)->all());
        $this->assertSame([], collect()->withSize(0)->all());
        $this->assertSame([], collect()->withSize(-5)->all());
    }

    // ---- Collection::insertAfterKey / insertBeforeKey --------------------

    public function test_insert_after_key_inserts_after_named_key(): void
    {
        $result = collect(['a' => 1, 'b' => 2, 'c' => 3])
            ->insertAfterKey('a', 99, 'x');

        $this->assertSame(['a' => 1, 'x' => 99, 'b' => 2, 'c' => 3], $result->all());
    }

    public function test_insert_after_key_appends_when_missing(): void
    {
        $result = collect(['a' => 1])->insertAfterKey('zzz', 99, 'x');

        $this->assertSame(['a' => 1, 'x' => 99], $result->all());
    }

    public function test_insert_before_key_inserts_before_named_key(): void
    {
        $result = collect(['a' => 1, 'b' => 2])
            ->insertBeforeKey('b', 99, 'x');

        $this->assertSame(['a' => 1, 'x' => 99, 'b' => 2], $result->all());
    }

    public function test_insert_before_key_prepends_when_missing(): void
    {
        $result = collect(['a' => 1])->insertBeforeKey('zzz', 99, 'x');

        $this->assertSame(['x' => 99, 'a' => 1], $result->all());
    }

    // ---- Collection::sectionBy -------------------------------------------

    public function test_section_by_groups_consecutive_runs(): void
    {
        $items = collect([
            ['type' => 'a', 'v' => 1],
            ['type' => 'a', 'v' => 2],
            ['type' => 'b', 'v' => 3],
            ['type' => 'a', 'v' => 4],
        ]);

        $sections = $items->sectionBy('type');

        $this->assertCount(3, $sections);
        $this->assertSame('a', $sections[0]->get(0));
        $this->assertSame(2, $sections[0]->get(1)->count());
        $this->assertSame('b', $sections[1]->get(0));
        $this->assertSame('a', $sections[2]->get(0));
        $this->assertSame(1, $sections[2]->get(1)->count());
    }

    public function test_section_by_accepts_a_callback_and_preserves_keys(): void
    {
        $sections = collect([10 => 'apple', 11 => 'avocado', 12 => 'banana'])
            ->sectionBy(fn (string $value): string => $value[0], true);

        $this->assertCount(2, $sections);
        $this->assertSame([10 => 'apple', 11 => 'avocado'], $sections[0]->get(1)->all());
        $this->assertSame([12 => 'banana'], $sections[1]->get(1)->all());
    }

    // ---- Str::stripTags ---------------------------------------------------

    public function test_strip_tags_removes_markup_with_optional_allow_list(): void
    {
        $this->assertSame('Hello world', Str::stripTags('<p>Hello <b>world</b></p>'));
        $this->assertSame('Hello <b>world</b>', Str::stripTags('<p>Hello <b>world</b></p>', '<b>'));
        $this->assertSame('Hello world', str('<p>Hello world</p>')->stripTags()->toString());
    }

    // ---- Str::linesCount --------------------------------------------------

    public function test_lines_count_counts_lines_across_newline_styles(): void
    {
        $this->assertSame(0, Str::linesCount(''));
        $this->assertSame(1, Str::linesCount('one line'));
        $this->assertSame(3, Str::linesCount("a\nb\nc"));
        $this->assertSame(2, Str::linesCount("a\r\nb"));
        $this->assertSame(2, Str::linesCount("a\rb"));
        $this->assertSame(2, str("a\nb")->linesCount());
    }

    // ---- Str::interpolate -------------------------------------------------

    public function test_interpolate_replaces_named_placeholders(): void
    {
        $this->assertSame(
            'Hello Ada, you are 30',
            Str::interpolate('Hello :name, you are :age', ['name' => 'Ada', 'age' => 30]),
        );
    }

    public function test_interpolate_replaces_longest_key_first(): void
    {
        // :foo_bar must win over :foo so the prefix isn't partially replaced.
        $this->assertSame(
            'X / Y',
            Str::interpolate(':foo / :foo_bar', ['foo' => 'X', 'foo_bar' => 'Y']),
        );

        $this->assertSame('hi', str(':greeting')->interpolate(['greeting' => 'hi'])->toString());
    }
}
