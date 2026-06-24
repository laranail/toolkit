<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Faker\Generator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * String / identity / miscellaneous value helpers.
 *
 * Folded into {@see Helper} — call via the
 * `Helper::` facade, never the trait directly.
 */
trait InteractsWithStrings
{
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

    /**
     * Generate a random, handle-style username.
     *
     * Unlike {@see usernameFromEmail()} / {@see nameToUsernames()} (which derive
     * a username from existing identity data), this produces a fresh anonymous
     * handle — a lowercase alphabetic prefix followed by a numeric suffix
     * (e.g. "user4821"), suitable for placeholder/guest accounts. The result is
     * always a valid identifier: it starts with a letter and contains only
     * `[a-z0-9]`.
     *
     * @param string $prefix Leading word; sanitised to `[a-z]`, falling back to
     *                       `user` when nothing usable remains.
     * @param int    $digits Number of trailing random digits (clamped to 1..10).
     */
    public static function generateUsername(string $prefix = 'user', int $digits = 4): string
    {
        $prefix = (string) preg_replace('/[^a-z]/', '', Str::lower($prefix));

        if ($prefix === '') {
            $prefix = 'user';
        }

        $digits = max(1, min(10, $digits));

        // Build the suffix one digit at a time: the first digit is 1..9 (so the
        // overall length is exactly $digits), the rest are 0..9. This avoids any
        // large 10**$digits arithmetic and keeps the result a fixed-width handle.
        $suffix = (string) random_int(1, 9);

        for ($i = 1; $i < $digits; $i++) {
            $suffix .= (string) random_int(0, 9);
        }

        return $prefix . $suffix;
    }

    public static function uuid(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Escape a dirty string (or array of strings, joined) into an
     * XSS-safe {@see HtmlString} via Laravel's `e()` helper. Null yields an
     * empty HtmlString. (Recovered as the legacy `html()` sanitiser.)
     *
     * @param string|array<int|string, mixed>|null $dirty
     */
    public static function escapeHtml(string|array|null $dirty): HtmlString
    {
        if ($dirty === null) {
            return new HtmlString('');
        }

        $value = is_array($dirty)
            ? implode('', array_map(static fn (mixed $item): string => Cast::toString($item), $dirty))
            : $dirty;

        return new HtmlString(e($value));
    }

    /**
     * The "basename" of a class — its short name without the namespace.
     */
    public static function classBasename(object|string $class): string
    {
        return class_basename($class);
    }

    /**
     * A random integer in `[$from, $to]` excluding any value in `$except`.
     *
     * Bounded (max attempts) so an impossible exclusion set throws rather than
     * recursing forever — the legacy version recursed unbounded.
     *
     * @param list<int> $except
     *
     * @throws RuntimeException when no allowed value is found within the bound
     */
    public static function randomIntExcept(int $from, int $to, array $except = []): int
    {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $maxAttempts = 1000;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $number = random_int($from, $to);

            if (!in_array($number, $except, true)) {
                return $number;
            }
        }

        throw new RuntimeException(
            sprintf('Could not find an allowed integer in [%d, %d] after %d attempts.', $from, $to, $maxAttempts),
        );
    }

    /**
     * A Faker generator for the given locale, via Laravel's `fake()` helper.
     */
    public static function faker(?string $locale = null): Generator
    {
        return fake($locale);
    }
}
