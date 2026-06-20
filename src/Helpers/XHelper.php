<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class XHelper
{
    // ------------------------
    // Array Helpers
    // ------------------------

    public static function arrayTrim(array $array): array
    {
        return array_map(fn ($value) => is_string($value) ? trim($value) : $value, $array);
    }

    /**
     * Flatten a multi-dimensional array into a single level of leaf values.
     *
     * @param array<mixed> $array
     *
     * @return array<int, mixed>
     */
    public static function arrayFlatten(array $array): array
    {
        return Arr::flatten($array);
    }

    // ------------------------
    // String Helpers
    // ------------------------

    public static function strBetween(string $string, string $start, string $end): ?string
    {
        $start = preg_quote($start, '/');
        $end = preg_quote($end, '/');

        $pattern = "/$start(.*?)$end/";
        preg_match($pattern, $string, $matches);

        return $matches[1] ?? null;
    }

    public static function strSlugify(string $string, string $separator = '-'): string
    {
        // Str::slug transliterates unicode (e.g. "Café" => "cafe").
        return Str::slug($string, $separator);
    }

    // ------------------------
    // Date Helpers
    // ------------------------

    public static function carbonParse($date, $format = 'Y-m-d H:i:s'): ?string
    {
        return Carbon::parse($date)->format($format);
    }

    public static function carbonHumanDiff($date): string
    {
        return Carbon::parse($date)->diffForHumans();
    }

    // ------------------------
    // Miscellaneous Helpers
    // ------------------------

    public static function uuid(): string
    {
        return Str::uuid()->toString();
    }
}
