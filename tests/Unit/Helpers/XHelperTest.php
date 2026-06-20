<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use Simtabi\Laranail\Toolkit\Helpers\XHelper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class XHelperTest extends TestCase
{
    public function test_array_trim_trims_only_string_values(): void
    {
        $this->assertSame(
            ['a', 'b', 3],
            XHelper::arrayTrim(['  a ', "b\n", 3]),
        );
    }

    public function test_array_flatten_collapses_nested_arrays(): void
    {
        $this->assertSame(
            [1, 2, 3, 4],
            XHelper::arrayFlatten([1, [2, [3, 4]]]),
        );
    }

    public function test_str_between_extracts_the_inner_substring(): void
    {
        $this->assertSame('value', XHelper::strBetween('[value]', '[', ']'));
        $this->assertNull(XHelper::strBetween('no markers', '[', ']'));
    }

    public function test_str_slugify_transliterates_unicode(): void
    {
        $this->assertSame('cafe-au-lait', XHelper::strSlugify('Café au lait'));
        $this->assertSame('hello-world', XHelper::strSlugify('Hello, World!'));
    }

    public function test_uuid_is_a_valid_v4_uuid(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            XHelper::uuid(),
        );
    }
}
