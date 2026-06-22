<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support\Diagnostics;

/**
 * Thin diagnostics surface for the toolkit's runtime requirements.
 *
 * Exposes the genuinely useful checks from the legacy `RequirementsChecker`:
 * the running PHP version against a floor, presence of required extensions,
 * and writability of directories. The directory-permission probe is the only
 * bespoke logic kept; everything else is a direct read of PHP runtime state.
 *
 * Designed to feed `php artisan about` via {@see toAboutArray()} — it does not
 * reimplement that command.
 */
final class RequirementsDiagnostics
{
    /**
     * The package's minimum supported PHP version (matches composer's `^8.4.1`
     * floor, inherited from the laranail/console dependency).
     */
    public const MINIMUM_PHP_VERSION = '8.4.1';

    /**
     * Extensions the toolkit relies on across its modules.
     *
     * @var list<string>
     */
    public const REQUIRED_EXTENSIONS = ['json', 'mbstring', 'fileinfo'];

    /**
     * Check the running PHP version against the supplied (or default) floor.
     *
     * @return array{current: string, minimum: string, supported: bool}
     */
    public function checkPhpVersion(?string $minimumVersion = null): array
    {
        $minimum = $minimumVersion ?? self::MINIMUM_PHP_VERSION;

        return [
            'current' => PHP_VERSION,
            'minimum' => $minimum,
            'supported' => version_compare(PHP_VERSION, $minimum, '>='),
        ];
    }

    /**
     * Report which of the given extensions are loaded.
     *
     * @param list<string>|null $extensions
     *
     * @return array<string, bool>
     */
    public function checkExtensions(?array $extensions = null): array
    {
        $results = [];

        foreach ($extensions ?? self::REQUIRED_EXTENSIONS as $extension) {
            $results[$extension] = extension_loaded($extension);
        }

        return $results;
    }

    /**
     * Probe whether each of the given directories is writable.
     *
     * This is the bespoke piece retained from the legacy checker: it resolves
     * a path's nearest existing ancestor when the path itself does not yet
     * exist, so "can I create files here" is answered correctly.
     *
     * @param list<string> $paths
     *
     * @return array<string, bool>
     */
    public function checkWritableDirectories(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            $results[$path] = $this->isDirectoryWritable($path);
        }

        return $results;
    }

    /**
     * Determine whether a directory (or the location it would be created in)
     * is writable.
     */
    public function isDirectoryWritable(string $path): bool
    {
        $candidate = $path;

        // Walk up to the nearest existing ancestor so a not-yet-created
        // directory is judged by where it would live.
        while ($candidate !== '' && !file_exists($candidate)) {
            $parent = \dirname($candidate);

            if ($parent === $candidate) {
                break;
            }

            $candidate = $parent;
        }

        return $candidate !== '' && is_dir($candidate) && is_writable($candidate);
    }

    /**
     * Report which of the given extensions are missing (the inverse view of
     * {@see checkExtensions()}), handy for a single "all good?" answer.
     *
     * @param list<string>|null $extensions
     *
     * @return list<string>
     */
    public function missingExtensions(?array $extensions = null): array
    {
        return array_keys(array_filter(
            $this->checkExtensions($extensions),
            static fn (bool $loaded): bool => !$loaded,
        ));
    }

    /**
     * Probe free disk space at the given path against an optional minimum.
     *
     * `disk_total_space` / `disk_free_space` can return false (unreadable path,
     * restricted SAPI); that is reported as `available: false` rather than
     * throwing, so the probe degrades gracefully.
     *
     * @return array{
     *     path: string,
     *     free: int|null,
     *     total: int|null,
     *     minimum: int|null,
     *     available: bool,
     *     sufficient: bool,
     * }
     */
    public function checkDiskSpace(?string $path = null, ?int $minimumBytes = null): array
    {
        $path ??= storage_path();

        $free = is_dir($path) ? disk_free_space($path) : false;
        $total = is_dir($path) ? disk_total_space($path) : false;

        $freeBytes = $free === false ? null : (int) $free;
        $totalBytes = $total === false ? null : (int) $total;
        $available = $freeBytes !== null;

        $sufficient = $available
            && ($minimumBytes === null || $freeBytes >= $minimumBytes);

        return [
            'path' => $path,
            'free' => $freeBytes,
            'total' => $totalBytes,
            'minimum' => $minimumBytes,
            'available' => $available,
            'sufficient' => $sufficient,
        ];
    }

    /**
     * Build the array surfaced under `php artisan about`.
     *
     * @return array<string, string>
     */
    public function toAboutArray(): array
    {
        $php = $this->checkPhpVersion();

        $missing = $this->missingExtensions();

        $disk = $this->checkDiskSpace();

        return [
            'PHP Version' => $php['current'],
            'Minimum PHP' => $php['minimum'],
            'PHP Supported' => $php['supported'] ? 'Yes' : 'No',
            'Required Extensions' => $missing === [] ? 'All loaded' : 'Missing: ' . implode(', ', $missing),
            'Storage Writable' => $this->isDirectoryWritable(storage_path()) ? 'Yes' : 'No',
            'Storage Free Space' => $disk['free'] === null ? 'Unknown' : $this->formatBytes($disk['free']),
        ];
    }

    /**
     * Human-readable byte size (binary units).
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / 1024 ** $power, 2) . ' ' . $units[$power];
    }
}
