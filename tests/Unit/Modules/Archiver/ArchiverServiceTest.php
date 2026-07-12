<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Archiver;

use PharData;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiveException;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Archiver as ArchiverFacade;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverService;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Tar;
use Simtabi\Laranail\Toolkit\Modules\Archiver\TarGz;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Zip;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class ArchiverServiceTest extends TestCase
{
    private string $work;

    protected function setUp(): void
    {
        parent::setUp();
        $this->work = sys_get_temp_dir() . '/laranail-archiver-svc-' . bin2hex(random_bytes(6));
        mkdir($this->work, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->work);
        parent::tearDown();
    }

    public function test_service_factory_methods_return_extractors(): void
    {
        $service = new ArchiverService();

        $this->assertInstanceOf(Tar::class, $service->tar());
        $this->assertInstanceOf(TarGz::class, $service->tarGz());
        $this->assertInstanceOf(Zip::class, $service->zip());
    }

    public function test_service_extract_delegates_to_manager(): void
    {
        $tarPath = $this->work . '/data.tar';
        $phar = new PharData($tarPath);
        $phar->addFromString('hello.txt', 'hi');
        unset($phar);

        $dest = $this->work . '/out';
        (new ArchiverService())->extract($tarPath, $dest);

        $this->assertFileExists($dest . '/hello.txt');
        $this->assertSame('hi', file_get_contents($dest . '/hello.txt'));
    }

    public function test_facade_proxies_to_the_service(): void
    {
        $this->assertInstanceOf(Zip::class, ArchiverFacade::zip());
        $this->assertInstanceOf(Tar::class, ArchiverFacade::tar());
        $this->assertInstanceOf(TarGz::class, ArchiverFacade::tarGz());
        $this->assertInstanceOf(ArchiverService::class, ArchiverFacade::getFacadeRoot());
    }

    public function test_exception_factories_produce_clear_messages(): void
    {
        $this->assertStringContainsString('Unable to open archive', ArchiveException::cannotOpen('/x.zip')->getMessage());
        $this->assertStringContainsString('unsafe archive entry', ArchiveException::unsafeEntry('../evil')->getMessage());
        $this->assertStringContainsString('exceeds the configured limit', ArchiveException::tooLarge()->getMessage());
        $this->assertStringContainsString('no archive extractor', ArchiveException::missingExtractor('rar')->getMessage());
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
