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

    // -----------------------------------------------------------------------
    // validateSize / validate (folded from the legacy DatabaseFileService)
    // -----------------------------------------------------------------------

    public function test_validate_size_passes_within_limit_and_fails_over_limit(): void
    {
        $service = $this->app->make(FileServiceInterface::class);

        $path = sys_get_temp_dir() . '/laranail_fs_' . uniqid() . '.txt';
        file_put_contents($path, str_repeat('a', 2048)); // 2 KB

        try {
            $this->assertTrue($service->validateSize($path, 1));   // <= 1 MB
            $this->assertTrue($service->validateSize($path, 0));   // no upper bound
            $this->assertFalse($service->validateSize($path . '.missing', 1));
        } finally {
            @unlink($path);
        }
    }

    public function test_validate_size_rejects_unsafe_path(): void
    {
        $service = $this->app->make(FileServiceInterface::class);

        $this->assertFalse($service->validateSize('../../etc/passwd', 100));
    }

    public function test_validate_combines_extension_and_size_checks(): void
    {
        $service = $this->app->make(FileServiceInterface::class);

        $sql = sys_get_temp_dir() . '/laranail_fs_' . uniqid() . '.sql';
        file_put_contents($sql, 'SELECT 1;');

        try {
            $this->assertTrue($service->validate($sql, ['sql']));
            $this->assertTrue($service->validate($sql, ['sql'], 10));
            $this->assertTrue($service->validate($sql, ['sql'], 0));        // 0 MB → no size cap
            $this->assertFalse($service->validate($sql, ['zip']));          // wrong ext
            $this->assertFalse($service->validate($sql . '.missing', ['sql']));
        } finally {
            @unlink($sql);
        }
    }

    public function test_validate_with_empty_allowlist_accepts_any_extension(): void
    {
        $service = $this->app->make(FileServiceInterface::class);

        $path = sys_get_temp_dir() . '/laranail_fs_' . uniqid() . '.weird';
        file_put_contents($path, 'data');

        try {
            $this->assertTrue($service->validate($path, []));
            $this->assertFalse($service->validate('../../etc/passwd', []));
        } finally {
            @unlink($path);
        }
    }
}
