<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

use ZipArchive;

final class Zip extends Extractor
{
    /**
     * The Unix mode bits ZIP stores in the high 16 of `external_attributes`,
     * and the file-type mask that identifies a symbolic link.
     */
    private const int UNIX_MODE_SHIFT = 16;

    private const int S_IFMT = 0o170000;

    private const int S_IFLNK = 0o120000;

    public function __construct(
        private readonly BoundedEntryWriter $writer = new BoundedEntryWriter,
    ) {}

    /**
     * ## On the declared sizes this guard trusts
     *
     * `statIndex()['size']` is the archive's own account of itself, and an
     * attacker writes that field. Summing it is still the right first check —
     * the real zip bomb is a small payload with an honestly declared huge size,
     * and refusing that before a byte lands is exactly what the sum does.
     *
     * What makes the sum sound against a *dishonest* declaration is that no
     * entry is allowed to produce more than it declared. Until 2026-10 this
     * rested on libzip truncating an understated entry to its declared size, and
     * on `extractTo()` quietly reporting success when it did. PHP 8.4.26 and
     * 8.5.11 changed the second half (php-src GH-23240): the truncated entry now
     * fails its CRC, `extractTo()` raises a warning, returns false, and leaves the
     * truncated file on disk. Still bounded, but the guard's property had come to
     * depend on which patch release was installed.
     *
     * It no longer does. Each entry is streamed through `BoundedEntryWriter`,
     * which reads at most the declared size, checks the declared CRC-32 itself,
     * and only renames a verified copy into place — so an understated, overstated
     * or corrupt entry is refused with an `ArchiveException` on every runtime,
     * and the files this call created are removed. The probe is
     * `ZipBombGuardTest`.
     */
    public function extract(string $pathToArchive, string $pathToDirectory): void
    {
        $archive = new ZipArchive;

        if ($archive->open($pathToArchive) !== true) {
            throw ArchiveException::cannotOpen($pathToArchive);
        }

        $this->ensureDestination($pathToDirectory);

        // Validate EVERY entry before writing anything (fail-closed Zip-Slip guard).
        $entries = [];
        $total = 0;
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $stat = $archive->statIndex($i);

            if ($stat === false) {
                $archive->close();

                throw ArchiveException::cannotOpen($pathToArchive);
            }

            $name = (string) $stat['name'];

            $this->assertWithinDestination($pathToDirectory, $name);
            $this->assertNotSymlink($archive, $i, $name);

            $total += (int) $stat['size'];
            $this->assertWithinLimits($i + 1, $total);

            $entries[] = [
                'index' => $i,
                'name'  => $name,
                'size'  => (int) $stat['size'],
                'crc'   => (int) $stat['crc'],
                'mtime' => (int) $stat['mtime'],
            ];
        }

        $created = [];

        try {
            foreach ($entries as $entry) {
                $this->extractEntry($archive, $pathToDirectory, $entry, $created);
            }
        } catch (ArchiveException $exception) {
            $this->rollBack($created);

            throw $exception;
        } finally {
            $archive->close();
        }
    }

    /**
     * @param array{index: int, name: string, size: int, crc: int, mtime: int} $entry
     * @param list<string> $created Paths this extraction created, oldest first.
     */
    private function extractEntry(ZipArchive $archive, string $directory, array $entry, array &$created): void
    {
        $target = rtrim($directory, '/') . '/' . $entry['name'];

        if (str_ends_with($entry['name'], '/')) {
            $this->makeDirectory(rtrim($target, '/'), $created);

            return;
        }

        $this->makeDirectory(dirname($target), $created);

        $existed = file_exists($target);
        $source = $archive->getStreamIndex($entry['index']);

        if ($source === false) {
            throw ArchiveException::corruptEntry($entry['name']);
        }

        try {
            $this->writer->write($source, $target, $entry['name'], $entry['size'], $entry['crc']);
        } finally {
            fclose($source);
        }

        if (! $existed) {
            $created[] = $target;
        }

        // extractTo() stamped each file with its entry's modification time; keep that.
        @touch($target, $entry['mtime']);
    }

    /**
     * @param list<string> $created
     */
    private function makeDirectory(string $directory, array &$created): void
    {
        $missing = [];

        for ($path = $directory; ! is_dir($path); $path = dirname($path)) {
            $missing[] = $path;
        }

        foreach (array_reverse($missing) as $path) {
            if (! @mkdir($path, 0755) && ! is_dir($path)) {
                throw new ArchiveException("Unable to create destination directory [{$path}].");
            }

            $created[] = $path;
        }
    }

    /**
     * Remove what this extraction created, newest first, so a refused archive
     * leaves the destination as it found it. Files that already existed are
     * left alone: they were replaced only by verified content.
     *
     * @param list<string> $created
     */
    private function rollBack(array $created): void
    {
        foreach (array_reverse($created) as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }
    }

    /**
     * Refuse an entry marked as a symbolic link.
     *
     * The tar path has always done this (`Extractor::validatePharEntries()`
     * checks `isLink()`); the ZIP path validated names only, while three
     * docblocks in this module claimed symlink safety for both. This makes the
     * claim true.
     *
     * PHP's current `extractTo()` materialises such an entry as a regular file
     * containing the link target rather than as a link, so this is not today's
     * traversal vector — `assertWithinDestination()` is. But that is a property
     * of the libzip build, not of the format, and the docs should not be
     * describing a guarantee that rests on it.
     */
    private function assertNotSymlink(ZipArchive $archive, int $index, string $name): void
    {
        $read = $archive->getExternalAttributesIndex($index, $opsys, $attr);

        if ($read !== true || $opsys !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        $mode = ((int) $attr) >> self::UNIX_MODE_SHIFT;

        if (($mode & self::S_IFMT) === self::S_IFLNK) {
            throw ArchiveException::unsafeEntry($name);
        }
    }
}
