<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

use ZipArchive;

final class Zip extends Extractor
{
    public function extract(string $pathToArchive, string $pathToDirectory): void
    {
        $archive = new ZipArchive();

        if ($archive->open($pathToArchive) !== true) {
            throw ArchiveException::cannotOpen($pathToArchive);
        }

        $this->ensureDestination($pathToDirectory);

        // Validate EVERY entry before writing anything (fail-closed Zip-Slip guard).
        $total = 0;
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $stat = $archive->statIndex($i);

            if ($stat === false) {
                $archive->close();

                throw ArchiveException::cannotOpen($pathToArchive);
            }

            $this->assertWithinDestination($pathToDirectory, (string) $stat['name']);

            $total += (int) $stat['size'];
            $this->assertWithinLimits($i + 1, $total);
        }

        if (!$archive->extractTo($pathToDirectory)) {
            $archive->close();

            throw ArchiveException::cannotOpen($pathToArchive);
        }

        $archive->close();
    }
}
