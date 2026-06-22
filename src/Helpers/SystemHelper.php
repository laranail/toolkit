<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers;

/**
 * Read-only system / runtime introspection helpers.
 *
 * Recovered from the legacy SystemService classes (see
 * docs/migration/RESTORE-CANDIDATES.md). Every method is side-effect free —
 * no config() mutation, no I/O beyond reading PHP/ini/$_SERVER state.
 */
final class SystemHelper
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
            'current_formatted' => FileHelper::formatFileSize($current),
            'peak_formatted' => FileHelper::formatFileSize($peak),
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
