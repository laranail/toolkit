<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Throwable;

/**
 * Read-only system / runtime introspection helpers.
 *
 * Every method is side-effect free — no config() mutation, no I/O beyond
 * reading PHP/ini/$_SERVER state. Folded into
 * {@see Helper} — call via the `Helper::`
 * facade, never the trait directly.
 */
trait InteractsWithSystem
{
    /**
     * Parse a PHP memory-limit string ("256M", "1G", "512K") into bytes.
     * Returns -1 for the "unlimited" sentinel.
     */
    public static function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        if ($memoryLimit === '' || $memoryLimit === '-1') {
            return -1;
        }

        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;

        return match ($last) {
            'g' => $value * (1024 ** 3),
            'm' => $value * (1024 ** 2),
            'k' => $value * 1024,
            default => $value,
        };
    }

    /** The configured memory_limit ini string (e.g. "256M", "-1"). */
    public static function memoryLimit(): string
    {
        $limit = ini_get('memory_limit');

        return $limit === '' ? '-1' : $limit;
    }

    /**
     * Current and peak memory usage, raw bytes plus human-readable strings.
     *
     * @return array{current: int, peak: int, limit: string, current_formatted: string, peak_formatted: string}
     */
    public static function memoryUsage(): array
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        return [
            'current' => $current,
            'peak' => $peak,
            'limit' => self::memoryLimit(),
            'current_formatted' => self::formatFileSize($current),
            'peak_formatted' => self::formatFileSize($peak),
        ];
    }

    /** The running PHP version string. */
    public static function phpVersion(): string
    {
        return PHP_VERSION;
    }

    /** Whether the running PHP version is >= the given minimum. */
    public static function isPhpVersionSupported(string $minimumVersion): bool
    {
        return version_compare(PHP_VERSION, $minimumVersion, '>=');
    }

    /** Whether PHP is running under the CLI SAPI. */
    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /** Whether the current request is served over HTTPS. */
    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    /** Alias of {@see isHttps()} — whether the request is served over TLS/SSL. */
    public static function isSslInstalled(): bool
    {
        return self::isHttps();
    }

    /**
     * The application's `composer.json`, decoded to an array. Returns an empty
     * array when the file is missing or unreadable (never throws).
     *
     * @return array<string, mixed>
     */
    public static function composer(): array
    {
        try {
            $path = base_path('composer.json');

            if (!File::exists($path)) {
                return [];
            }

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode(File::get($path), true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The constraint declared for a package in the app's `composer.json`
     * `require` / `require-dev`, or null when it is not a direct dependency.
     */
    public static function composerPackageVersion(string $package): ?string
    {
        $composer = self::composer();

        foreach (['require', 'require-dev'] as $section) {
            $requirements = $composer[$section] ?? null;

            if (is_array($requirements) && isset($requirements[$package]) && is_string($requirements[$package])) {
                return $requirements[$package];
            }
        }

        return null;
    }

    /**
     * A consolidated snapshot of the runtime: PHP, OS, SAPI, Laravel version
     * and the current application environment.
     *
     * @return array{php_version: string, os: string, sapi: string, laravel_version: string, env: string}
     */
    public static function systemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
            'sapi' => PHP_SAPI,
            'laravel_version' => app()->version(),
            'env' => app()->environment(),
        ];
    }

    /**
     * A read-only snapshot of server/runtime environment settings.
     *
     * @return array<string, mixed>
     */
    public static function serverEnv(): array
    {
        return [
            'https' => self::isHttps(),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'php_extensions' => get_loaded_extensions(),
            'memory_limit' => self::memoryLimit(),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => error_reporting(),
            'timezone' => date_default_timezone_get(),
        ];
    }
}
