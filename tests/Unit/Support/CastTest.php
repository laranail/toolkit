<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use Simtabi\Laranail\Toolkit\Support\Cast;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CastTest extends TestCase
{
    public function test_to_string_narrows_scalars_and_falls_back(): void
    {
        $this->assertSame('abc', Cast::toString('abc'));
        $this->assertSame('5', Cast::toString(5));
        $this->assertSame('1.5', Cast::toString(1.5));
        $this->assertSame('1', Cast::toString(true));
        $this->assertSame('0', Cast::toString(false));
        $this->assertSame('', Cast::toString(null));
        $this->assertSame('fallback', Cast::toString(['x'], 'fallback'));
    }

    public function test_to_int_accepts_numeric_strings_only(): void
    {
        $this->assertSame(5, Cast::toInt(5));
        $this->assertSame(5, Cast::toInt('5'));
        $this->assertSame(3, Cast::toInt(3.9));
        $this->assertSame(0, Cast::toInt('abc'));
        $this->assertSame(7, Cast::toInt(null, 7));
        $this->assertSame(7, Cast::toInt([], 7));
    }

    public function test_to_float_accepts_numeric_values(): void
    {
        $this->assertSame(5.0, Cast::toFloat(5));
        $this->assertSame(5.5, Cast::toFloat('5.5'));
        $this->assertSame(1.0, Cast::toFloat(1.0));
        $this->assertSame(2.5, Cast::toFloat('abc', 2.5));
        $this->assertSame(2.5, Cast::toFloat(true, 2.5));
    }

    public function test_to_bool_uses_filter_var_semantics(): void
    {
        $this->assertTrue(Cast::toBool(true));
        $this->assertTrue(Cast::toBool('true'));
        $this->assertTrue(Cast::toBool('1'));
        $this->assertTrue(Cast::toBool('yes'));
        $this->assertTrue(Cast::toBool('on'));
        $this->assertFalse(Cast::toBool('false'));
        $this->assertFalse(Cast::toBool('0'));
        $this->assertFalse(Cast::toBool('no'));
    }

    public function test_to_bool_falls_back_on_unrecognised_input(): void
    {
        $this->assertFalse(Cast::toBool('maybe'));
        $this->assertTrue(Cast::toBool('maybe', true));
        // filter_var(null) yields false (not null), so the default does not apply.
        $this->assertFalse(Cast::toBool(null, true));
    }
}
