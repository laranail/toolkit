<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('http')]
class RequestMacrosEdgeTest extends TestCase
{
    public function test_has_any_returns_false_for_empty_key_list(): void
    {
        $request = Request::create('/', 'GET', ['a' => 1]);

        self::assertFalse($request->hasAny([]));
    }

    public function test_has_any_short_circuits_on_first_present_key(): void
    {
        $request = Request::create('/', 'GET', ['b' => 2]);

        self::assertTrue($request->hasAny(['missing', 'b']));
        self::assertFalse($request->hasAny(['missing', 'gone']));
    }

    public function test_merge_if_missing_returns_the_same_request_instance(): void
    {
        $request = Request::create('/', 'GET', ['a' => 1]);

        $returned = $request->mergeIfMissing(['b' => 2]);

        self::assertSame($request, $returned);
    }

    public function test_merge_if_missing_preserves_existing_and_fills_gaps(): void
    {
        $request = Request::create('/', 'GET', ['a' => 1]);

        $request->mergeIfMissing(['a' => 99, 'b' => 2, 'c' => 3]);

        // Existing key untouched; only missing keys are merged in.
        self::assertSame(1, $request->input('a'));
        self::assertSame(2, $request->input('b'));
        self::assertSame(3, $request->input('c'));
    }

    public function test_merge_if_missing_with_empty_values_is_a_no_op(): void
    {
        $request = Request::create('/', 'GET', ['a' => 1]);

        $request->mergeIfMissing([]);

        self::assertSame(['a' => 1], $request->all());
    }
}
