<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers;

use InvalidArgumentException;

/**
 * Geospatial helpers. Native (no third-party geo dependency).
 *
 * Recovers the legacy `DistanceBetween` macro as a clean static helper, since
 * it was never wired to any Macroable target.
 */
final class GeoHelper
{
    /** Mean Earth radius in kilometres (used as the Haversine base unit). */
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Per-unit conversion factor from kilometres.
     */
    private const UNITS = [
        'km' => 1.0,
        'mi' => 0.621371192,
        'm' => 1000.0,
        'nmi' => 0.539956803,
    ];

    /**
     * Great-circle distance between two lat/lng points via the Haversine
     * formula, returned in `$unit` (km | mi | m | nmi).
     *
     * @throws InvalidArgumentException for an unsupported unit
     */
    public static function distanceBetween(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
        string $unit = 'km',
    ): float {
        $unit = strtolower($unit);

        if (!isset(self::UNITS[$unit])) {
            throw new InvalidArgumentException(
                sprintf('Unsupported unit [%s]; expected one of: %s.', $unit, implode(', ', array_keys(self::UNITS))),
            );
        }

        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        $angle = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $angle * self::UNITS[$unit];
    }
}
