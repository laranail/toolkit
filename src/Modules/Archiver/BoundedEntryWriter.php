<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

/**
 * Writes one archive entry to disk without trusting the library that decodes it.
 *
 * The bomb guard sums the sizes an archive declares and refuses one whose total
 * exceeds the ceiling. That only holds if no entry can produce more bytes than it
 * declared, and whether it can is a property of the zip library underneath:
 * libzip truncates an understated entry to its declared size, and PHP 8.4.26 /
 * 8.5.11 began reporting the CRC mismatch that truncation causes as a failed
 * extraction (php-src GH-23240) instead of a silent success. Two runtimes, two
 * behaviours, one guard that must hold on both.
 *
 * So the bound is enforced here, by counting: never more than the declared size
 * is read, one extra byte is probed to detect an entry that runs past it, and the
 * result is checked against the declared CRC-32. A read error, a short entry, a
 * long entry and a checksum mismatch are all the same answer — an
 * `ArchiveException`, with nothing left at the target path. The copy goes to a
 * temporary file beside the target and is renamed into place only once verified,
 * so a partial file is never visible under the entry's name.
 *
 * @internal
 */
final class BoundedEntryWriter
{
    private const int CHUNK_BYTES = 65_536;

    /**
     * @param resource $source The entry's decoded content.
     *
     * @return int The number of bytes written, which is always `$declaredSize`.
     *
     * @throws ArchiveException When the content is not exactly what was declared.
     */
    public function write(mixed $source, string $target, string $entryName, int $declaredSize, int $declaredCrc): int
    {
        $temporary = @tempnam(dirname($target), '.laranail-extract-');

        if ($temporary === false) {
            throw ArchiveException::corruptEntry($entryName);
        }

        try {
            $this->copyVerified($source, $temporary, $entryName, $declaredSize, $declaredCrc);

            if (! @rename($temporary, $target)) {
                throw ArchiveException::corruptEntry($entryName);
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return $declaredSize;
    }

    /**
     * @param resource $source
     */
    private function copyVerified(mixed $source, string $temporary, string $entryName, int $declaredSize, int $declaredCrc): void
    {
        $sink = @fopen($temporary, 'wb');

        if ($sink === false) {
            throw ArchiveException::corruptEntry($entryName);
        }

        $crc = hash_init('crc32b');
        $written = 0;

        try {
            while ($written < $declaredSize) {
                $chunk = @fread($source, max(1, min(self::CHUNK_BYTES, $declaredSize - $written)));

                if ($chunk === false) {
                    throw ArchiveException::corruptEntry($entryName);
                }

                if ($chunk === '') {
                    break;
                }

                if (@fwrite($sink, $chunk) !== strlen($chunk)) {
                    throw ArchiveException::corruptEntry($entryName);
                }

                hash_update($crc, $chunk);
                $written += strlen($chunk);
            }

            // Read one byte past the declared end. Content there means the entry
            // is larger than it claims; a failure there is the library reporting
            // corruption it only detects at end of stream.
            $overflow = @fread($source, 1);

            if ($overflow === false || $overflow !== '' || $written !== $declaredSize) {
                throw ArchiveException::corruptEntry($entryName);
            }

            if (hexdec(hash_final($crc)) !== $declaredCrc) {
                throw ArchiveException::corruptEntry($entryName);
            }
        } finally {
            fclose($sink);
        }
    }
}
