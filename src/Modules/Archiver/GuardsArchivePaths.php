<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

/**
 * Shared protection against Zip-Slip / path-traversal during extraction.
 */
trait GuardsArchivePaths
{
    /**
     * Ensure an archive entry name resolves *inside* the destination directory.
     * Rejects absolute paths, drive-letter paths and `..` traversal — checked
     * lexically (the target does not exist on disk yet).
     */
    protected function assertWithinDestination(string $destination, string $entryName): void
    {
        $entryName = str_replace('\\', '/', $entryName);

        // Absolute (unix) or Windows drive-letter paths are never allowed.
        if (str_starts_with($entryName, '/') || preg_match('#^[a-zA-Z]:#', $entryName) === 1) {
            throw ArchiveException::unsafeEntry($entryName);
        }

        $base = $this->lexicalPath($destination);
        $target = $this->lexicalPath($destination . '/' . $entryName);

        if ($target !== $base && !str_starts_with($target, $base . '/')) {
            throw ArchiveException::unsafeEntry($entryName);
        }
    }

    /**
     * Resolve `.`/`..` segments lexically, without touching the filesystem.
     */
    private function lexicalPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $isAbsolute = str_starts_with($path, '/');

        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $segment;
        }

        return ($isAbsolute ? '/' : '') . implode('/', $parts);
    }
}
