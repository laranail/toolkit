<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use Simtabi\Laranail\Toolkit\Support\Config;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('toolkit_test', [
            'name' => 'laranail',
            'int_as_string' => '42',
            'real_int' => 7,
            'float' => 1.5,
            'flag_true' => 'yes',
            'flag_false' => '0',
            'list' => ['a', 1, 'b', null, 'c'],
            'map' => ['k' => 'v'],
            'object_value' => new \stdClass(),
        ]);
    }

    public function test_string_narrows_and_falls_back(): void
    {
        $this->assertSame('laranail', Config::string('toolkit_test.name'));
        $this->assertSame('7', Config::string('toolkit_test.real_int'));
        $this->assertSame('fallback', Config::string('toolkit_test.missing', 'fallback'));
        $this->assertSame('fallback', Config::string('toolkit_test.object_value', 'fallback'));
    }

    public function test_int_accepts_numeric_strings(): void
    {
        $this->assertSame(42, Config::int('toolkit_test.int_as_string'));
        $this->assertSame(7, Config::int('toolkit_test.real_int'));
        $this->assertSame(99, Config::int('toolkit_test.name', 99));
        $this->assertSame(99, Config::int('toolkit_test.missing', 99));
    }

    public function test_float_accepts_numeric_values(): void
    {
        $this->assertSame(1.5, Config::float('toolkit_test.float'));
        $this->assertSame(42.0, Config::float('toolkit_test.int_as_string'));
        $this->assertSame(3.5, Config::float('toolkit_test.name', 3.5));
    }

    public function test_bool_uses_filter_var_semantics(): void
    {
        $this->assertTrue(Config::bool('toolkit_test.flag_true'));
        $this->assertFalse(Config::bool('toolkit_test.flag_false'));
        $this->assertTrue(Config::bool('toolkit_test.missing', true));
    }

    public function test_array_falls_back_for_non_arrays(): void
    {
        $this->assertSame(['k' => 'v'], Config::array('toolkit_test.map'));
        $this->assertSame(['x'], Config::array('toolkit_test.name', ['x']));
    }

    public function test_string_list_drops_non_string_members(): void
    {
        $this->assertSame(['a', 'b', 'c'], Config::stringList('toolkit_test.list'));
        $this->assertSame(['v'], Config::stringList('toolkit_test.map'));
        $this->assertSame(['d'], Config::stringList('toolkit_test.name', ['d']));
        $this->assertSame(['d'], Config::stringList('toolkit_test.missing', ['d']));
    }
}
