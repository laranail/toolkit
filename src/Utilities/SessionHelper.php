<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Utilities;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

/**
 * Query-string "filter key" helpers, recovered from the legacy SessionService.
 *
 * A filter key is an `&`-joined list of filter tokens carried in the query
 * string (e.g. `status=active&sort=name`). These helpers add, test and remove
 * tokens idiomatically, plus a small bridge for persisting a request value into
 * both the session and a JavaScript-readable cookie.
 *
 * Kept distinct from {@see FilteringUtil} (which filters in-memory collections)
 * because this operates on the query-string/session layer.
 */
final class SessionHelper
{
    /**
     * Whether `$value` is one of the `&`-joined tokens in `$key`.
     */
    public static function existsInFilterKey(string $key, mixed $value = null): bool
    {
        return Collection::make(explode('&', $key))->contains($value);
    }

    /**
     * Join the given (non-null) values into a single `&`-joined filter key.
     */
    public static function joinInFilterKey(mixed ...$value): string
    {
        return Collection::make($value)
            ->reject(static fn (mixed $item): bool => $item === null)
            ->implode('&');
    }

    /**
     * Remove `$value` (and the reserved `page` token) from an existing
     * `&`-joined filter key. Returns the rebuilt key, or null when nothing
     * remains.
     */
    public static function removeFromFilterKey(string $oldValues, mixed $value): ?string
    {
        $remaining = Collection::make(explode('&', $oldValues))
            ->reject(static fn (string $token): bool => $token === (string) $value || $token === 'page')
            ->values();

        return $remaining->isEmpty() ? null : $remaining->implode('&');
    }

    /**
     * Persist a request input value into the session and queue a cookie of the
     * same name so client-side JavaScript can read it. No-op when the input is
     * absent.
     */
    public static function saveJavaScriptCookies(string $cookieName, int $duration = 60): void
    {
        $value = Request::input($cookieName);

        if ($value === null) {
            return;
        }

        Session::put($cookieName, $value);
        Cookie::queue($cookieName, (string) $value, $duration);
    }
}
