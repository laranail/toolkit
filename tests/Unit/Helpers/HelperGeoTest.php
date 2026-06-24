<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class HelperGeoTest extends TestCase
{
    public function test_distance_between_london_and_paris_in_km(): void
    {
        // London (51.5074, -0.1278) to Paris (48.8566, 2.3522): ~343 km.
        $distance = Helper::distanceBetween(51.5074, -0.1278, 48.8566, 2.3522);

        $this->assertEqualsWithDelta(343.0, $distance, 5.0);
    }

    public function test_distance_is_zero_for_identical_points(): void
    {
        $this->assertSame(0.0, Helper::distanceBetween(10.0, 20.0, 10.0, 20.0));
    }

    public function test_unit_conversion_is_consistent(): void
    {
        $km = Helper::distanceBetween(51.5074, -0.1278, 48.8566, 2.3522, 'km');
        $mi = Helper::distanceBetween(51.5074, -0.1278, 48.8566, 2.3522, 'mi');
        $m = Helper::distanceBetween(51.5074, -0.1278, 48.8566, 2.3522, 'm');
        $nmi = Helper::distanceBetween(51.5074, -0.1278, 48.8566, 2.3522, 'nmi');

        $this->assertEqualsWithDelta($km * 0.621371192, $mi, 0.001);
        $this->assertEqualsWithDelta($km * 1000, $m, 0.001);
        $this->assertEqualsWithDelta($km * 0.539956803, $nmi, 0.001);
    }

    public function test_unsupported_unit_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Helper::distanceBetween(0.0, 0.0, 1.0, 1.0, 'furlongs');
    }
}
