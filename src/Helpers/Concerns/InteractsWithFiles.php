<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Helpers\Helper;

/**
 * Pure file-name / size inspection helpers.
 *
 * These inspect/format strings only — they touch no filesystem. For
 * path-traversal safety use Support\FilePathGuard; for file I/O use the Storage
 * facade / Traits\FileProcessingTrait. Folded into
 * {@see Helper} — call via the `Helper::`
 * facade, never the trait directly.
 */
trait InteractsWithFiles
{
    /** @var list<string> Extensions treated as images by {@see isImage()}. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];

    /** Human-readable byte size, e.g. 1024 → "1 KB". */
    public static function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $size = (float) $bytes;
        $i = 0;

        for (; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

    /** The lower-cased extension of a path, without the dot. */
    public static function extension(string $path): string
    {
        return Str::lower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /** The basename of a path without its extension. */
    public static function filenameWithoutExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Whether the path has a common image extension.
     *
     * Fixes the legacy bug `Arr::has($list, $value)` (a key check on a value
     * list) — uses a strict in_array value check.
     */
    public static function isImage(string $path): bool
    {
        return in_array(self::extension($path), self::IMAGE_EXTENSIONS, true);
    }

    /**
     * Strip path separators, null bytes and unsafe characters from a file NAME
     * (not a path). Apply after basename(), before storing an uploaded name.
     */
    public static function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(['/', '\\', "\0"], '', $filename);

        return (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    }
}
