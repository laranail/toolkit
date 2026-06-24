<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Support\FilePathGuard;

/**
 * File-name / size inspection plus thin, path-guarded filesystem probes.
 *
 * The string helpers (extension, filenameWithoutExtension, isImage,
 * sanitizeFilename, formatFileSize) touch no filesystem. The probes (exists,
 * size, lastModified, fileInfo, toDataUri, fromJson) are exception-safe,
 * read-only wrappers over the `File` facade, each guarded against `..`/null-byte
 * paths via the canonical {@see FilePathGuard} (no re-implementation here). I/O
 * that merely passes through to Storage/File — read/write/copy/move/delete — is
 * deliberately NOT folded; call those facades directly.
 *
 * This is the primary, injectable file domain (formerly the static
 * `Helper::*` file helpers). Resolve via {@see FileServiceInterface}.
 */
final class FileService implements FileServiceInterface
{
    use FilePathGuard;

    /** @var list<string> Extensions treated as images by {@see isImage()}. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];

    public function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $size = (float) $bytes;
        $i = 0;

        for (; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

    public function extension(string $path): string
    {
        return Str::lower(pathinfo($path, PATHINFO_EXTENSION));
    }

    public function filenameWithoutExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Whether the path has a common image extension.
     *
     * Fixes the legacy bug `Arr::has($list, $value)` (a key check on a value
     * list) — uses a strict in_array value check.
     */
    public function isImage(string $path): bool
    {
        return in_array($this->extension($path), self::IMAGE_EXTENSIONS, true);
    }

    public function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(['/', '\\', "\0"], '', $filename);

        return (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    }

    public function exists(string $path): bool
    {
        if (!$this->isSafePath($path)) {
            return false;
        }

        return File::exists($path);
    }

    public function size(string $path): int
    {
        if (!$this->exists($path) || !File::isFile($path)) {
            return 0;
        }

        return File::size($path);
    }

    public function lastModified(string $path): int
    {
        if (!$this->exists($path)) {
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
    public function hasAllowedExtension(string $path, array $allowed): bool
    {
        $normalized = array_map(static fn (string $ext): string => Str::lower(ltrim($ext, '.')), $allowed);

        return in_array($this->extension($path), $normalized, true);
    }

    /**
     * Inspect a file, returning its path/size/extension/name metadata.
     *
     * Generic replacement for the legacy DB-specific getFileInfo(): returns an
     * empty array for a missing file or an unsafe path rather than throwing.
     *
     * @return array{path: string, size: int, extension: string, name: string, basename: string, last_modified: int, is_readable: bool, is_writable: bool}|array{}
     */
    public function fileInfo(string $path): array
    {
        if (!$this->exists($path) || !File::isFile($path)) {
            return [];
        }

        return [
            'path' => $path,
            'size' => $this->size($path),
            'extension' => $this->extension($path),
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'basename' => basename($path),
            'last_modified' => $this->lastModified($path),
            'is_readable' => File::isReadable($path),
            'is_writable' => File::isWritable($path),
        ];
    }

    /**
     * Generate a random file name with the given extension, e.g.
     * generateName('pdf') → "Xa9...q2.pdf". The leading dot on the extension is
     * optional. Restores the legacy GenerateName macro as a service method.
     */
    public function generateName(string $extension, int $length = 25): string
    {
        $length = max(1, $length);
        $extension = ltrim(trim($extension), '.');
        $name = Str::random($length);

        return $extension === '' ? $name : $name . '.' . $extension;
    }

    /**
     * Encode an existing file as a base64 `data:` URI
     * ("data:<mime>;base64,<payload>"), or an empty string when the file is
     * missing or the path is unsafe. Restores the legacy ToBase64 macro safely.
     */
    public function toDataUri(string $path): string
    {
        if (!$this->exists($path) || !File::isFile($path)) {
            return '';
        }

        $mime = File::mimeType($path);
        $contents = File::get($path);

        return sprintf('data:%s;base64,%s', $mime === false ? 'application/octet-stream' : $mime, base64_encode($contents));
    }

    /**
     * Read JSON from a file path (when one exists at $pathOrContent) or treat the
     * argument as a raw JSON string, decoding to an associative array. Returns
     * null on a missing/unsafe path or invalid JSON. Restores the legacy FromJson
     * macro with proper error handling (the legacy version ignored decode errors).
     *
     * @return array<int|string, mixed>|null
     */
    public function fromJson(string $pathOrContent): ?array
    {
        $content = $this->exists($pathOrContent) && File::isFile($pathOrContent)
            ? File::get($pathOrContent)
            : $pathOrContent;

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
