<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Security;

use RuntimeException;

/**
 * Lazy, app-free accessor for the package's bundled security datasets.
 *
 * Reads the merged {@see config/security.php} file (passwords, passphrase
 * wordlist, redaction keys) on first access and statically caches it per
 * process — at most one `require` per file per process.
 *
 * Designed to work WITHOUT a booted Laravel application (the Security generators
 * are pure value objects with standalone unit tests), so it never calls
 * `config()` or `config_path()` unguarded:
 *
 *  - The PACKAGE DEFAULT is always loaded via a `__DIR__`-relative path, which
 *    resolves with no framework present.
 *  - A PUBLISHED override at `config_path('laranail-toolkit-security.php')` is
 *    used INSTEAD only when `config_path()` exists (Laravel is booted) AND the
 *    published file is present.
 *
 * @see config/security.php
 */
final class SecurityData
{
    /** EFF Large Wordlist size (6^5) — asserted on load. */
    public const WORDLIST_SIZE = 7776;

    /**
     * Process-wide cache of the loaded security config array. One `require`
     * per process; `null` until first access. Typed loosely because the array
     * is loaded from an arbitrary (publishable) file at runtime and validated
     * defensively by the accessors.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $config = null;

    /**
     * Return the lowercased, deduplicated common-password denylist.
     *
     * @return list<string>
     */
    public static function commonPasswords(): array
    {
        return self::stringList(self::section('passwords')['common'] ?? null);
    }

    /**
     * Return the EFF Large Wordlist (exactly 7776 CC0 words).
     *
     * @throws RuntimeException when the list is not exactly 7776 words
     *
     * @return list<string>
     */
    public static function passphraseWords(): array
    {
        $list = self::stringList(self::section('passphrases')['wordlist'] ?? null);

        if (count($list) !== self::WORDLIST_SIZE) {
            throw new RuntimeException(sprintf(
                'EFF wordlist must contain exactly %d words, found %d.',
                self::WORDLIST_SIZE,
                count($list),
            ));
        }

        return $list;
    }

    /**
     * Return the default request-data redaction keys.
     *
     * @return list<string>
     */
    public static function redactKeys(): array
    {
        return self::stringList(self::config()['redact_keys'] ?? null);
    }

    /**
     * Return a top-level config section as an array (empty array when absent or
     * malformed).
     *
     * @return array<string, mixed>
     */
    private static function section(string $key): array
    {
        $value = self::config()[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Coerce an arbitrary value into a `list<string>`, dropping non-strings.
     *
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Load (once per process) and return the security config array. Prefers a
     * published override when Laravel is booted and the file exists; otherwise
     * falls back to the package default. Never calls `config_path()` unguarded.
     *
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // Package default — resolves with no booted app:
        //   src/Modules/Security/ --(dirname __DIR__, 3)--> repo root --> config/security.php
        $path = dirname(__DIR__, 3) . '/config/security.php';

        // Prefer a published override ONLY when Laravel is booted (config_path
        // exists) AND the override file is present.
        if (function_exists('config_path')) {
            $published = config_path('laranail-toolkit-security.php');

            if (is_file($published)) {
                $path = $published;
            }
        }

        if (!is_file($path)) {
            throw new RuntimeException("Security config not found at [{$path}].");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new RuntimeException("Security config at [{$path}] must return an array.");
        }

        /** @var array<string, mixed> $config */
        return self::$config = $config;
    }
}
