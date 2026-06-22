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

    /**
     * Title-case a string with multibyte awareness (falls back to Str::ucwords
     * when the mbstring extension is unavailable).
     */
    public static function ucWords(string $string, string $encoding = 'UTF-8'): string
    {
        if (!function_exists('mb_convert_case')) {
            return Str::ucwords($string);
        }

        return mb_convert_case($string, MB_CASE_TITLE, $encoding);
    }

    /**
     * Derive a username from an email address: the local part stripped to
     * `[A-Za-z0-9._]`, prefixed with `user_` when it doesn't start with a
     * letter, and falling back to `user` when nothing usable remains.
     */
    public static function usernameFromEmail(string $email): string
    {
        $username = (string) preg_replace('/[^a-zA-Z0-9._]/', '', Str::before($email, '@'));

        if ($username !== '' && !ctype_alpha($username[0])) {
            $username = 'user_' . $username;
        }

        return $username !== '' ? $username : 'user';
    }

    /**
     * Build an email address from a username. A value already containing `@` is
     * returned unchanged; otherwise the username is sanitised and the domain
     * appended.
     */
    public static function emailFromUsername(string $username, string $domain = 'example.com'): string
    {
        if (Str::contains($username, '@')) {
            return $username;
        }

        $username = (string) preg_replace('/[^a-zA-Z0-9._-]/', '', $username);

        return $username . '@' . $domain;
    }

    /**
     * Generate a deterministic list of username suggestions from a person's
     * name using only native Str helpers (no third-party name library).
     *
     * Combines first/last parts and initials, slug-normalised, plus a couple of
     * numeric-suffixed variants so callers can pick the first available one.
     *
     * @return list<string>
     */
    public static function nameToUsernames(string $firstName, ?string $lastName = null): array
    {
        $first = Str::lower(Str::slug($firstName, ''));
        $last = $lastName !== null ? Str::lower(Str::slug($lastName, '')) : '';

        if ($first === '' && $last === '') {
            return [];
        }

        $candidates = [];

        if ($last !== '') {
            $candidates[] = $first . $last;
            $candidates[] = $first . '.' . $last;
            $candidates[] = $first . '_' . $last;

            if ($first !== '') {
                $candidates[] = Str::substr($first, 0, 1) . $last;
                $candidates[] = $first . Str::substr($last, 0, 1);
            }
        }

        if ($first !== '') {
            $candidates[] = $first;
        }

        if ($last !== '') {
            $candidates[] = $last;
        }

        // Every candidate above is added under a non-empty guard, so the list
        // holds only non-empty strings; just de-duplicate and re-index.
        $base = $candidates[0];
        $candidates[] = $base . random_int(10, 99);
        $candidates[] = $base . random_int(100, 999);

        return array_values(array_unique($candidates));
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
