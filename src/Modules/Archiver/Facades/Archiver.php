<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Contracts\ArchiverServiceInterface;

/**
 * @method static \Simtabi\Laranail\Toolkit\Modules\Archiver\Services\Tar   tar()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Archiver\Services\TarGz tarGz()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Archiver\Services\Zip   zip()
 * @method static void                                                      extract(string $pathToArchive, string $pathToDirectory)
 *
 * @see ArchiverServiceInterface
 */
class Archiver extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ArchiverServiceInterface::class;
    }
}
