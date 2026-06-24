<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Support\FilePathGuard;

/**
 * File-name / size inspection plus thin, path-guarded filesystem probes.
 *
 * The string helpers (extension, filenameWithoutExtension, isImage,
 * sanitizeFilename, formatFileSize) touch no filesystem. The probes (exists,
 * size, lastModified, fileInfo) are exception-safe, read-only wrappers over the
 * `File` facade, each guarded against `..`/null-byte paths via the canonical
 * {@see FilePathGuard} (no re-implementation here). I/O that merely passes
 * through to Storage/File — read/write/copy/move/delete — is deliberately NOT
 * folded; call those facades directly. Folded into {@see Helper}: call via the
 * `Helper::` facade, never the trait directly.
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

    /**
     * Whether a file exists at the given path.
     *
     * Read-only, exception-safe wrapper over the `File` facade. Returns false
     * for unsafe paths (`..` traversal segments or null bytes) without throwing.
     */
    public static function exists(string $path): bool
    {
        if (!self::isSafePath($path)) {
            return false;
        }

        return File::exists($path);
    }

    /**
     * The size of a file in bytes, or 0 when it is missing or the path is unsafe.
     *
     * Read-only, exception-safe wrapper over the `File` facade.
     */
    public static function size(string $path): int
    {
        if (!self::exists($path) || !File::isFile($path)) {
            return 0;
        }

        return File::size($path);
    }

    /**
     * The file's last-modified UNIX timestamp, or 0 when missing/unsafe.
     *
     * Read-only, exception-safe wrapper over the `File` facade.
     */
    public static function lastModified(string $path): int
    {
        if (!self::exists($path)) {
            return 0;
        }

        return File::lastModified($path);
    }

    /**
     * Whether the path's (lower-cased) extension is in the allowed list.
     *
     * Generic replacement for the legacy DB-specific extension check — pass
     * whatever allow-list the caller needs. Fixes the legacy bug
     * `Arr::has($list, $value)` (a key check against a value list) by using a
     * strict in_array value comparison against lower-cased extensions.
     *
     * @param list<string> $allowed
     */
    public static function hasAllowedExtension(string $path, array $allowed): bool
    {
        $normalized = array_map(static fn (string $ext): string => Str::lower(ltrim($ext, '.')), $allowed);

        return in_array(self::extension($path), $normalized, true);
    }

    /**
     * Inspect a file, returning its path/size/extension/name metadata.
     *
     * Generic replacement for the legacy DB-specific getFileInfo(): returns an
     * empty array for a missing file or an unsafe path rather than throwing.
     *
     * @return array{path: string, size: int, extension: string, name: string, basename: string, last_modified: int, is_readable: bool, is_writable: bool}|array{}
     */
    public static function fileInfo(string $path): array
    {
        if (!self::exists($path) || !File::isFile($path)) {
            return [];
        }

        return [
            'path' => $path,
            'size' => self::size($path),
            'extension' => self::extension($path),
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'basename' => basename($path),
            'last_modified' => self::lastModified($path),
            'is_readable' => File::isReadable($path),
            'is_writable' => File::isWritable($path),
        ];
    }

    /**
     * Whether a path is free of `..` traversal segments and null bytes.
     *
     * Reuses (does not re-implement) the canonical {@see FilePathGuard}; the
     * guard exposes instance methods, so it is wrapped in a throwaway object
     * since this helper is stateless/static.
     */
    private static function isSafePath(string $path): bool
    {
        $guard = new class()
        {
            use FilePathGuard;
        };

        return $guard->isSafePath($path);
    }
}
