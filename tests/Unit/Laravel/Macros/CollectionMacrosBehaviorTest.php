<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use ArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Exhaustive behaviour tests for the toolkit's Collection macros — exact
 * input to output assertions (incl. empty input, boundaries, off-by-one,
 * even/odd, recursion) so the mutation gate has no surviving operator, return,
 * boundary or cast mutants. One method (roughly) per macro.
 */
final class CollectionMacrosBehaviorTest extends TestCase
{
    public function test_transpose_swaps_rows_and_columns(): void
    {
        $this->assertSame([[1, 3], [2, 4]], collect([[1, 2], [3, 4]])->transpose()->all());
        // Empty collection transposes to an empty collection.
        $this->assertSame([], collect([])->transpose()->all());
        // Scalar rows are coerced to single-element arrays before transposing.
        $this->assertSame([[1, 2]], collect([1, 2])->transpose()->all());
    }

    public function test_recursive_wraps_nested_arrays_and_objects(): void
    {
        $result = collect(['a' => ['b' => 1], 'o' => (object) ['x' => 2], 'n' => 3])->recursive();

        $this->assertInstanceOf(Collection::class, $result->get('a'));
        $this->assertInstanceOf(Collection::class, $result->get('o'));
        $this->assertSame(3, $result->get('n'));
        $this->assertSame(['a' => ['b' => 1], 'o' => ['x' => 2], 'n' => 3], $result->toArray());

        // Scalars pass straight through with no wrapping.
        $this->assertSame([1, 2, 3], collect([1, 2, 3])->recursive()->all());
    }

    public function test_map_to_key_uses_callback_pair_as_key_and_value(): void
    {
        $result = collect([
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
        ])->mapToKey(static fn (array $row): array => [$row['id'], $row['name']])->all();

        $this->assertSame([1 => 'a', 2 => 'b'], $result);
    }

    public function test_map_to_key_passes_key_to_callback(): void
    {
        $result = collect(['x' => 'a', 'y' => 'b'])
            ->mapToKey(static fn (string $value, string $key): array => [$key, $value])
            ->all();

        $this->assertSame(['x' => 'a', 'y' => 'b'], $result);
    }

    public function test_filter_recursive_without_callback_drops_falsy_recursively(): void
    {
        $this->assertSame(
            ['a' => [1 => 1], 'c' => 2],
            collect(['a' => [0, 1], 'b' => 0, 'c' => 2])->filterRecursive()->toArray(),
        );
    }

    public function test_filter_recursive_applies_callback(): void
    {
        $this->assertSame(
            [2, 4],
            collect([1, 2, 3, 4])->filterRecursive(static fn (int $v): bool => $v % 2 === 0)->values()->all(),
        );
    }

    public function test_first_or_fail_returns_first_match(): void
    {
        $this->assertSame(1, collect([1, 2, 3])->firstOrFail());
        $this->assertSame(2, collect([1, 2, 3])->firstOrFail(static fn (int $v): bool => $v > 1));
    }

    public function test_first_or_fail_throws_on_empty(): void
    {
        // Note: firstOrFail is a native Collection method in this Laravel version,
        // so the macro is shadowed; the native throws an ItemNotFoundException
        // (a RuntimeException) rather than the macro's custom message.
        $this->expectException(RuntimeException::class);

        collect([])->firstOrFail();
    }

    public function test_first_or_fail_throws_when_no_match(): void
    {
        $this->expectException(RuntimeException::class);

        collect([1, 2])->firstOrFail(static fn (int $v): bool => $v > 5);
    }

    public function test_sum_recursive_flattens_then_sums(): void
    {
        $this->assertSame(15, collect([[1, 2], [3, [4, 5]]])->sumRecursive());
        $this->assertSame(0, collect([])->sumRecursive());
    }

    public function test_average_by_maps_then_averages(): void
    {
        // Identity over [1,2,3,4] → 10 / 4 → 2.5 (float).
        $this->assertSame(2.5, collect([1, 2, 3, 4])->averageBy(static fn (int $v): int => $v));
        // Doubled → [2,4,6,8] → 20 / 4 → 5 (int, evenly divisible).
        $this->assertSame(5, collect([1, 2, 3, 4])->averageBy(static fn (int $v): int => $v * 2));
    }

    public function test_to_csv_wraps_each_field_with_enclosure(): void
    {
        $this->assertSame(['"a","b"', '"c","d"'], collect([['a', 'b'], ['c', 'd']])->toCsv());
        // Custom delimiter, with int values stringified.
        $this->assertSame(['"1";"2"'], collect([[1, 2]])->toCsv(';'));
        // Custom enclosure.
        $this->assertSame(['|1|,|2|'], collect([[1, 2]])->toCsv(',', '|'));
        // Default escape (backslash) doubles the enclosure inside a field.
        $this->assertSame(['"a\\"b"'], collect([['a"b']])->toCsv());
        // Custom escape character.
        $this->assertSame(['"a#"b"'], collect([['a"b']])->toCsv(',', '"', '#'));
    }

    public function test_prioritize_moves_matches_to_the_front(): void
    {
        $this->assertSame(
            [2, 4, 1, 3],
            collect([1, 2, 3, 4])->prioritize(static fn (int $n): bool => $n % 2 === 0)->values()->all(),
        );
    }

    public function test_rotate_left_shifts_items_to_the_left(): void
    {
        $this->assertSame([], collect([])->rotateLeft()->all());
        $this->assertSame([2, 3, 1], collect([1, 2, 3])->rotateLeft()->values()->all());
        $this->assertSame([3, 1, 2], collect([1, 2, 3])->rotateLeft(2)->values()->all());
        // count modulo size: 4 % 3 == 1.
        $this->assertSame([2, 3, 1], collect([1, 2, 3])->rotateLeft(4)->values()->all());
        // count 0 is a no-op.
        $this->assertSame([1, 2, 3], collect([1, 2, 3])->rotateLeft(0)->values()->all());
    }

    public function test_rotate_right_shifts_items_to_the_right(): void
    {
        $this->assertSame([], collect([])->rotateRight()->all());
        $this->assertSame([3, 1, 2], collect([1, 2, 3])->rotateRight()->values()->all());
        $this->assertSame([2, 3, 1], collect([1, 2, 3])->rotateRight(2)->values()->all());
    }

    public function test_to_tree_nests_children_under_parents(): void
    {
        $tree = collect([
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
        ])->toTree();

        // groupBy reindexes each group, so children are sequentially keyed. Use a
        // JSON round-trip to fully recurse the nested children Collections.
        $this->assertSame(
            [
                [
                    'id' => 1,
                    'parent_id' => null,
                    'children' => [
                        ['id' => 2, 'parent_id' => 1, 'children' => []],
                    ],
                ],
            ],
            json_decode((string) json_encode($tree), true),
        );
    }

    public function test_insert_after_by_key_value_pair(): void
    {
        // String keys: the searched key is overwritten in place (see report note).
        $this->assertSame(
            ['a' => 1, 'b' => 99, 'c' => 3],
            collect(['a' => 1, 'b' => 2, 'c' => 3])->insertAfter('b', 99)->all(),
        );
        // Missing key falls back to put() (append).
        $this->assertSame(
            ['a' => 1, 'z' => 9],
            collect(['a' => 1])->insertAfter('z', 9)->all(),
        );
    }

    public function test_insert_before_by_key_value_pair(): void
    {
        // String keys: the trailing slice re-overwrites, so this is effectively a no-op.
        $this->assertSame(
            ['a' => 1, 'b' => 2, 'c' => 3],
            collect(['a' => 1, 'b' => 2, 'c' => 3])->insertBefore('b', 99)->all(),
        );
        // Missing key prepends.
        $this->assertSame(
            ['z' => 9, 'a' => 1],
            collect(['a' => 1])->insertBefore('z', 9)->all(),
        );
    }

    public function test_collect_by_wraps_value_at_key(): void
    {
        $this->assertSame([1, 2, 3], collect(['items' => [1, 2, 3]])->collectBy('items')->all());
        // Null value yields an empty collection.
        $this->assertSame([], collect(['x' => null])->collectBy('x')->all());
        // Scalar value is wrapped as a single item.
        $this->assertSame([5], collect(['x' => 5])->collectBy('x')->all());
        // Missing key uses the default, which is then wrapped.
        $this->assertSame(['def'], collect([])->collectBy('x', 'def')->all());
    }

    public function test_filter_map_maps_then_drops_falsy(): void
    {
        $this->assertSame(
            [2, 4],
            collect([1, 2, 3, 4])->filterMap(static fn (int $v): int => $v % 2 === 0 ? $v : 0)->values()->all(),
        );
    }

    public function test_if_any_runs_callback_only_when_not_empty(): void
    {
        $called = false;
        $collection = collect([1, 2]);
        $returned = $collection->ifAny(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertSame($collection, $returned);

        $calledOnEmpty = false;
        collect([])->ifAny(function () use (&$calledOnEmpty): void {
            $calledOnEmpty = true;
        });

        $this->assertFalse($calledOnEmpty);
    }

    public function test_none_is_the_inverse_of_contains(): void
    {
        $this->assertTrue(collect([1, 2, 3])->none(5));
        $this->assertFalse(collect([1, 2, 3])->none(2));

        $people = collect([['role' => 'admin'], ['role' => 'user']]);
        $this->assertFalse($people->none('role', 'admin'));
        $this->assertTrue($people->none('role', 'ghost'));
    }

    public function test_pluck_to_array_returns_plain_array(): void
    {
        $this->assertSame([1, 2], collect([['id' => 1], ['id' => 2]])->pluckToArray('id'));
        $this->assertSame(
            [1 => 'a', 2 => 'b'],
            collect([['id' => 1, 'n' => 'a'], ['id' => 2, 'n' => 'b']])->pluckToArray('n', 'id'),
        );
    }

    public function test_with_size_builds_a_sequential_range(): void
    {
        $this->assertSame([1, 2, 3], collect()->withSize(3)->all());
        $this->assertSame([1], collect()->withSize(1)->all());
        $this->assertSame([], collect()->withSize(0)->all());
        $this->assertSame([], collect()->withSize(-1)->all());
    }

    public function test_insert_after_key_preserves_keys(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2, 'x' => 99, 'c' => 3],
            collect(['a' => 1, 'b' => 2, 'c' => 3])->insertAfterKey('b', 99, 'x')->all(),
        );
        // Without an explicit key the item is appended numerically after the anchor.
        $this->assertSame(
            ['a' => 1, 0 => 99, 'b' => 2],
            collect(['a' => 1, 'b' => 2])->insertAfterKey('a', 99)->all(),
        );
        // Missing anchor appends.
        $this->assertSame(
            ['a' => 1, 'x' => 9],
            collect(['a' => 1])->insertAfterKey('z', 9, 'x')->all(),
        );
    }

    public function test_insert_before_key_preserves_keys(): void
    {
        $this->assertSame(
            ['a' => 1, 'x' => 99, 'b' => 2, 'c' => 3],
            collect(['a' => 1, 'b' => 2, 'c' => 3])->insertBeforeKey('b', 99, 'x')->all(),
        );
        // Missing anchor prepends.
        $this->assertSame(
            ['x' => 9, 'a' => 1],
            collect(['a' => 1])->insertBeforeKey('z', 9, 'x')->all(),
        );
    }

    public function test_section_by_splits_consecutive_runs(): void
    {
        $this->assertSame(
            [
                ['x', [['t' => 'x', 'n' => 1], ['t' => 'x', 'n' => 2]]],
                ['y', [['t' => 'y', 'n' => 3]]],
            ],
            collect([
                ['t' => 'x', 'n' => 1],
                ['t' => 'x', 'n' => 2],
                ['t' => 'y', 'n' => 3],
            ])->sectionBy('t')->toArray(),
        );
    }

    public function test_section_by_can_preserve_keys_and_use_custom_section_keys(): void
    {
        $this->assertSame(
            [
                ['a', ['p' => 'a', 'q' => 'a']],
                ['b', ['r' => 'b']],
            ],
            collect(['p' => 'a', 'q' => 'a', 'r' => 'b'])->sectionBy(static fn (string $v): string => $v, true)->toArray(),
        );

        $this->assertSame(
            [
                ['name' => 'a', 'rows' => ['a']],
                ['name' => 'b', 'rows' => ['b']],
            ],
            collect(['a', 'b'])->sectionBy(static fn (string $v): string => $v, false, 'name', 'rows')->toArray(),
        );
    }

    public function test_where_contains_keeps_matching_string_values(): void
    {
        $this->assertSame(
            [['n' => 'hello'], ['n' => 'world']],
            collect([['n' => 'hello'], ['n' => 'world'], ['n' => 123]])
                ->whereContains('n', 'o')
                ->values()
                ->all(),
        );
        // Case-sensitive by default: a case mismatch is excluded.
        $this->assertSame([], collect([['n' => 'Hello']])->whereContains('n', 'hello')->values()->all());
        // Case-insensitive lowercases BOTH sides.
        $this->assertSame(
            [['n' => 'hello']],
            collect([['n' => 'hello']])->whereContains('n', 'HELLO', false)->values()->all(),
        );
    }

    public function test_where_starts_with_keeps_matching_string_values(): void
    {
        $this->assertSame(
            [['n' => 'apple']],
            collect([['n' => 'apple'], ['n' => 'grape'], ['n' => 5]])
                ->whereStartsWith('n', 'ap')
                ->values()
                ->all(),
        );
        $this->assertSame([], collect([['n' => 'Apple']])->whereStartsWith('n', 'ap')->values()->all());
        $this->assertSame(
            [['n' => 'apple']],
            collect([['n' => 'apple']])->whereStartsWith('n', 'AP', false)->values()->all(),
        );
    }

    public function test_where_ends_with_keeps_matching_string_values(): void
    {
        $this->assertSame(
            [['n' => 'cat']],
            collect([['n' => 'cat'], ['n' => 'dog'], ['n' => 9]])
                ->whereEndsWith('n', 'at')
                ->values()
                ->all(),
        );
        $this->assertSame([], collect([['n' => 'CAT']])->whereEndsWith('n', 'at')->values()->all());
        $this->assertSame(
            [['n' => 'cat']],
            collect([['n' => 'cat']])->whereEndsWith('n', 'AT', false)->values()->all(),
        );
    }

    public function test_before_returns_the_previous_item(): void
    {
        $this->assertSame(1, collect([1, 2, 3])->before(2));
        $this->assertSame(2, collect([1, 2, 3])->before(3));
        // No predecessor for the first item.
        $this->assertNull(collect([1, 2, 3])->before(1));
        // Absent value.
        $this->assertNull(collect([1, 2, 3])->before(99));
    }

    public function test_insert_at_inserts_at_a_positional_index(): void
    {
        $this->assertSame(['a', 'x', 'b', 'c'], collect(['a', 'b', 'c'])->insertAt(1, 'x')->all());
        $this->assertSame(['x', 'a', 'b', 'c'], collect(['a', 'b', 'c'])->insertAt(0, 'x')->all());
        $this->assertSame(['a', 'b', 'c', 'x'], collect(['a', 'b', 'c'])->insertAt(3, 'x')->all());
    }

    public function test_rotate_handles_positive_negative_and_empty(): void
    {
        $this->assertSame([2, 3, 4, 1], collect([1, 2, 3, 4])->rotate(1)->all());
        $this->assertSame([4, 1, 2, 3], collect([1, 2, 3, 4])->rotate(-1)->all());
        $this->assertSame([1, 2, 3, 4], collect([1, 2, 3, 4])->rotate(0)->all());
        $this->assertSame([1, 2, 3, 4], collect([1, 2, 3, 4])->rotate(4)->all());
        $this->assertSame([], collect([])->rotate(1)->all());
    }

    public function test_first_or_push_returns_match_without_pushing(): void
    {
        $collection = collect([1, 2, 3]);
        $this->assertSame(2, $collection->firstOrPush(static fn (int $v): bool => $v > 1, 99));
        $this->assertSame([1, 2, 3], $collection->all());

        // A falsy-but-non-null match (0) is still returned, not pushed.
        $zero = collect([0]);
        $this->assertSame(0, $zero->firstOrPush(static fn (int $v): bool => $v === 0, 99));
        $this->assertSame([0], $zero->all());
    }

    public function test_first_or_push_pushes_resolved_value_when_no_match(): void
    {
        $collection = collect([1, 2]);
        $resolved = $collection->firstOrPush(static fn (int $v): bool => $v > 5, static fn (): int => 42);

        $this->assertSame(42, $resolved);
        $this->assertSame([1, 2, 42], $collection->all());

        // Pushes onto an explicit instance instead of $this.
        $source = collect([1]);
        $target = collect([]);
        $source->firstOrPush(static fn (int $v): bool => false, 7, $target);
        $this->assertSame([1], $source->all());
        $this->assertSame([7], $target->all());
    }

    public function test_each_cons_yields_overlapping_windows(): void
    {
        $this->assertSame([[1, 2], [2, 3], [3, 4]], collect([1, 2, 3, 4])->eachCons(2)->toArray());
        $this->assertSame([[1, 2, 3], [2, 3, 4]], collect([1, 2, 3, 4])->eachCons(3)->toArray());
        // Window equal to length yields a single window.
        $this->assertSame([[1, 2, 3, 4]], collect([1, 2, 3, 4])->eachCons(4)->toArray());
        // Window larger than length yields nothing.
        $this->assertSame([], collect([1, 2, 3, 4])->eachCons(5)->toArray());
        // preserveKeys keeps the original keys inside each window.
        $this->assertSame([[1, 2], [1 => 2, 2 => 3]], collect([1, 2, 3])->eachCons(2, true)->toArray());
    }

    public function test_slice_before_splits_when_callback_is_true(): void
    {
        $this->assertSame(
            [[1, 3], [2, 4]],
            collect([1, 3, 2, 4])->sliceBefore(static fn (int $item, int $prev): bool => $item < $prev)->toArray(),
        );
        $this->assertSame([], collect([])->sliceBefore(static fn (int $item, int $prev): bool => true)->toArray());
        $this->assertSame([[5]], collect([5])->sliceBefore(static fn (int $item, int $prev): bool => true)->toArray());
        $this->assertSame(
            [[10 => 1, 20 => 3], [30 => 2]],
            collect([10 => 1, 20 => 3, 30 => 2])
                ->sliceBefore(static fn (int $item, int $prev): bool => $item < $prev, true)
                ->toArray(),
        );
    }

    public function test_chunk_by_groups_while_callback_result_is_stable(): void
    {
        $this->assertSame([[1, 1], [2, 2], [3]], collect([1, 1, 2, 2, 3])->chunkBy(static fn (int $v): int => $v)->toArray());
        $this->assertSame(
            [[['t' => 'a'], ['t' => 'a']], [['t' => 'b']]],
            collect([['t' => 'a'], ['t' => 'a'], ['t' => 'b']])
                ->chunkBy(static fn (array $i): string => $i['t'])
                ->toArray(),
        );
    }

    public function test_group_by_model_groups_rows_by_model_key(): void
    {
        $a = new CollectionMacroGroupable(['id' => 1]);
        $b = new CollectionMacroGroupable(['id' => 1]);
        $c = new CollectionMacroGroupable(['id' => 2]);

        $result = collect([$a, $b, $c])->groupByModel(static fn (CollectionMacroGroupable $m): CollectionMacroGroupable => $m);

        $this->assertCount(2, $result);
        $this->assertSame($a, $result[0][0]);
        $this->assertSame([$a, $b], $result[0][1]->values()->all());
        $this->assertSame($c, $result[1][0]);
        $this->assertSame([$c], $result[1][1]->values()->all());
    }

    public function test_group_by_model_supports_string_resolver_and_custom_keys(): void
    {
        $a = new CollectionMacroGroupable(['id' => 1]);
        $c = new CollectionMacroGroupable(['id' => 2]);

        $result = collect([['m' => $a], ['m' => $c]])->groupByModel('m', 'model', 'rows');

        // The model is resolved for the modelKey; rows hold the original items.
        $this->assertSame($a, $result[0]['model']);
        $this->assertSame([['m' => $a]], $result[0]['rows']->values()->all());
        $this->assertSame($c, $result[1]['model']);
    }

    public function test_group_by_model_buckets_keyless_models_together(): void
    {
        $x = new CollectionMacroGroupable();
        $y = new CollectionMacroGroupable();

        $result = collect([$x, $y])->groupByModel(static fn (CollectionMacroGroupable $m): CollectionMacroGroupable => $m);

        $this->assertCount(1, $result);
        $this->assertSame([$x, $y], $result[0][1]->values()->all());
    }

    public function test_for_select_box_sorts_case_insensitively_and_keys_by_id(): void
    {
        $this->assertSame(
            ['' => '', 1 => 'alpha', 2 => 'Beta'],
            collect([
                ['id' => 2, 'name' => 'Beta'],
                ['id' => 1, 'name' => 'alpha'],
            ])->forSelectBox('id', 'name'),
        );

        // Without the empty option.
        $this->assertSame(
            [1 => 'alpha', 2 => 'Beta'],
            collect([
                ['id' => 2, 'name' => 'Beta'],
                ['id' => 1, 'name' => 'alpha'],
            ])->forSelectBox('id', 'name', false),
        );
    }

    public function test_extract_pulls_keys_in_order_with_null_for_missing(): void
    {
        $this->assertSame([1, 3], collect(['a' => 1, 'b' => 2, 'c' => 3])->extract(['a', 'c'])->all());
        $this->assertSame([1, null], collect(['a' => 1])->extract(['a', 'z'])->all());
        // Variadic form.
        $this->assertSame([1, 2], collect(['a' => 1, 'b' => 2])->extract('a', 'b')->all());
        // Dot-path resolution.
        $this->assertSame([5], collect(['a' => ['b' => 5]])->extract('a.b')->all());
    }

    public function test_tail_returns_everything_but_the_first(): void
    {
        $this->assertSame([2, 3], collect([1, 2, 3])->tail()->all());
        $this->assertSame([1 => 2, 2 => 3], collect([1, 2, 3])->tail(true)->all());
        $this->assertSame([], collect([1])->tail()->all());
        $this->assertSame([], collect([])->tail()->all());
    }

    public function test_to_pairs_and_from_pairs_round_trip(): void
    {
        $this->assertSame([['a', 1], ['b', 2]], collect(['a' => 1, 'b' => 2])->toPairs()->all());
        $this->assertSame(['a' => 1, 'b' => 2], collect([['a', 1], ['b', 2]])->fromPairs()->all());
    }

    public function test_from_pairs_skips_non_array_pairs_and_bad_keys(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2],
            collect([['a', 1], 'nope', ['b', 2]])->fromPairs()->all(),
        );
        $this->assertSame(
            ['b' => 2],
            collect([[['x'], 1], ['b', 2]])->fromPairs()->all(),
        );
    }

    public function test_if_empty_runs_callback_only_when_empty(): void
    {
        $called = false;
        $collection = collect([]);
        $returned = $collection->ifEmpty(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertSame($collection, $returned);

        $calledWhenFull = false;
        collect([1])->ifEmpty(function () use (&$calledWhenFull): void {
            $calledWhenFull = true;
        });

        $this->assertFalse($calledWhenFull);
    }

    public function test_map_key_value_pairs_builds_associative_collection(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2],
            collect([['key' => 'a', 'value' => 1], ['key' => 'b', 'value' => 2]])->mapKeyValuePairs()->all(),
        );
        // Rows with a null key are skipped.
        $this->assertSame(
            ['b' => 2],
            collect([['key' => null, 'value' => 9], ['key' => 'b', 'value' => 2]])->mapKeyValuePairs()->all(),
        );
    }

    public function test_pluck_many_narrows_each_item_to_the_given_keys(): void
    {
        // Plain arrays.
        $this->assertSame(
            [['id' => 1, 'name' => 'A']],
            collect([['id' => 1, 'name' => 'A', 'secret' => 'x']])->pluckMany(['id', 'name'])->all(),
        );

        // Nested Collections.
        $this->assertSame(
            ['id' => 1, 'n' => 'a'],
            collect([collect(['id' => 1, 'n' => 'a', 'x' => 9])])->pluckMany(['id', 'n'])->first()->all(),
        );

        // ArrayAccess (ArrayObject).
        $this->assertSame(
            ['id' => 1, 'name' => 'A'],
            collect([new ArrayObject(['id' => 1, 'name' => 'A', 'secret' => 'x'])])->pluckMany(['id', 'name'])->first(),
        );

        // Plain objects.
        $this->assertEquals(
            (object) ['id' => 9, 'name' => 'C'],
            collect([(object) ['id' => 9, 'name' => 'C', 'secret' => 'z']])->pluckMany(['id', 'name'])->first(),
        );

        // Scalars pass through unchanged.
        $this->assertSame([5], collect([5])->pluckMany(['a'])->all());
    }

    public function test_replace_in_keys_rewrites_keys_and_keeps_values(): void
    {
        $this->assertSame(
            ['id' => 1, 'name' => 'A'],
            collect(['user_id' => 1, 'user_name' => 'A'])->replaceInKeys('user_', '')->all(),
        );
        // Array search/replace and integer keys cast to string.
        $this->assertSame(
            ['a_b' => 1, 'c_d' => 2],
            collect(['a-b' => 1, 'c.d' => 2])->replaceInKeys(['-', '.'], ['_', '_'])->all(),
        );
        $this->assertSame(['#' => 'x'], collect([5 => 'x'])->replaceInKeys('5', '#')->all());
    }

    public function test_sort_search_results_orders_by_relevance_score(): void
    {
        $result = collect([
            ['name' => 'zzz'],
            ['name' => 'xappx'],
            ['name' => 'apple'],
            ['name' => 'app'],
        ])->sortSearchResults('app', 'name');

        // exact (+100) > starts-with (+50) > contains (+25) > similar_text weight.
        $this->assertSame(['app', 'apple', 'xappx', 'zzz'], $result->pluck('name')->all());
    }

    public function test_sort_search_results_trims_and_lowercases_terms(): void
    {
        $result = collect([
            ['name' => 'BETA'],
            ['name' => 'alpha'],
        ])->sortSearchResults('  ALPHA ', 'name');

        $this->assertSame(['alpha', 'BETA'], $result->pluck('name')->all());
    }
}

/**
 * Named fixture model for the groupByModel macro tests — no DB access is needed
 * because getKey() reads the in-memory id attribute.
 */
class CollectionMacroGroupable extends Model
{
    protected $table = 'collection_macro_groupables';

    public $timestamps = false;

    protected $guarded = [];
}
