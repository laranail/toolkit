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
     * The package's minimum supported PHP version.
     */
    public const MINIMUM_PHP_VERSION = '8.3.0';

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
     * Build the array surfaced under `php artisan about`.
     *
     * @return array<string, string>
     */
    public function toAboutArray(): array
    {
        $php = $this->checkPhpVersion();

        $extensions = $this->checkExtensions();
        $missing = array_keys(array_filter($extensions, static fn (bool $loaded): bool => !$loaded));

        return [
            'PHP Version' => $php['current'],
            'Minimum PHP' => $php['minimum'],
            'PHP Supported' => $php['supported'] ? 'Yes' : 'No',
            'Required Extensions' => $missing === [] ? 'All loaded' : 'Missing: ' . implode(', ', $missing),
            'Storage Writable' => $this->isDirectoryWritable(storage_path()) ? 'Yes' : 'No',
        ];
    }
}
