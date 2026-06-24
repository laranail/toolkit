<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\FileService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class FileServiceTest extends TestCase
{
    public function test_interface_is_bound_as_a_singleton_concrete(): void
    {
        $a = $this->app->make(FileServiceInterface::class);
        $b = $this->app->make(FileServiceInterface::class);

        $this->assertInstanceOf(FileService::class, $a);
        $this->assertSame($a, $b, 'FileService should be a singleton.');
    }

    public function test_inspection_helpers_are_filesystem_free(): void
    {
        $service = $this->app->make(FileServiceInterface::class);

        $this->assertSame('pdf', $service->extension('/x/y/Report.PDF'));
        $this->assertSame('Report', $service->filenameWithoutExtension('/x/y/Report.PDF'));
        $this->assertTrue($service->isImage('a.png'));
        $this->assertSame('1 KB', $service->formatFileSize(1024));
    }

    public function test_probes_reject_traversal_paths(): void
    {
        $service = $this->app->make(FileServiceInterface::class);

        $this->assertFalse($service->exists('../../etc/passwd'));
        $this->assertSame(0, $service->size('../../etc/passwd'));
        $this->assertSame([], $service->fileInfo('../../etc/passwd'));
    }
}
