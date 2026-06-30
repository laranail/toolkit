<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Archiver;

use PharData;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiveException;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverService;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Tar;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Zip;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use ZipArchive;

class ArchiverTest extends TestCase
{
    private string $work;

    protected function setUp(): void
    {
        parent::setUp();
        $this->work = sys_get_temp_dir() . '/laranail-archiver-' . bin2hex(random_bytes(6));
        mkdir($this->work, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->work);
        parent::tearDown();
    }

    public function test_it_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(ArchiverService::class, $this->app->make(ArchiverServiceInterface::class));
    }

    public function test_zip_round_trip_extracts_safe_entries(): void
    {
        $zipPath = $this->work . '/safe.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'hi');
        $zip->addFromString('nested/world.txt', 'earth');
        $zip->close();

        $dest = $this->work . '/out-zip';
        (new Zip())->extract($zipPath, $dest);

        $this->assertSame('hi', file_get_contents($dest . '/hello.txt'));
        $this->assertSame('earth', file_get_contents($dest . '/nested/world.txt'));
    }

    #[Group('security')]
    public function test_zip_slip_entry_is_refused_and_nothing_escapes(): void
    {
        $zipPath = $this->work . '/evil.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('../evil.txt', 'pwned');
        $zip->addFromString('safe.txt', 'ok');
        $zip->close();

        $dest = $this->work . '/out-evil';

        try {
            (new Zip())->extract($zipPath, $dest);
            $this->fail('Expected ArchiveException for the traversal entry.');
        } catch (ArchiveException $e) {
            $this->assertStringContainsString('unsafe', strtolower($e->getMessage()));
        }

        // The traversal target must NOT have been written, and extraction is fail-closed.
        $this->assertFileDoesNotExist($this->work . '/evil.txt');
        $this->assertFileDoesNotExist($dest . '/safe.txt');
    }

    public function test_corrupt_zip_throws_cannot_open(): void
    {
        $bad = $this->work . '/corrupt.zip';
        file_put_contents($bad, 'not a real zip');

        $this->expectException(ArchiveException::class);
        (new Zip())->extract($bad, $this->work . '/out-corrupt');
    }

    public function test_tar_round_trip_extracts_safe_entries(): void
    {
        $tarPath = $this->work . '/safe.tar';
        $phar = new PharData($tarPath);
        $phar->addFromString('hello.txt', 'hi');
        $phar->addFromString('nested/world.txt', 'earth');
        unset($phar);

        $dest = $this->work . '/out-tar';
        (new Tar())->extract($tarPath, $dest);

        $this->assertSame('hi', file_get_contents($dest . '/hello.txt'));
        $this->assertSame('earth', file_get_contents($dest . '/nested/world.txt'));
    }

    public function test_corrupt_tar_throws_cannot_open(): void
    {
        $bad = $this->work . '/corrupt.tar';
        file_put_contents($bad, 'not a real tar archive');

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/Unable to open archive/');
        (new Tar())->extract($bad, $this->work . '/out-corrupt-tar');
    }

    public function test_set_limits_is_fluent_and_returns_the_same_instance(): void
    {
        $zip = new Zip();

        $this->assertSame($zip, $zip->setLimits(5, 1024));
    }

    #[Group('security')]
    public function test_zip_extraction_is_refused_when_entry_count_exceeds_the_limit(): void
    {
        $zipPath = $this->work . '/many.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('a.txt', 'a');
        $zip->addFromString('b.txt', 'b');
        $zip->close();

        $dest = $this->work . '/out-many';

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/exceeds the configured limit/');
        (new Zip())->setLimits(1, 1_073_741_824)->extract($zipPath, $dest);
    }

    #[Group('security')]
    public function test_zip_extraction_is_refused_when_total_bytes_exceeds_the_limit(): void
    {
        $zipPath = $this->work . '/big.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('payload.txt', str_repeat('x', 64));
        $zip->close();

        $dest = $this->work . '/out-big';

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/exceeds the configured limit/');
        (new Zip())->setLimits(10_000, 8)->extract($zipPath, $dest);
    }

    #[Group('security')]
    public function test_tar_extraction_is_refused_when_total_bytes_exceeds_the_limit(): void
    {
        $tarPath = $this->work . '/big.tar';
        $phar = new PharData($tarPath);
        $phar->addFromString('payload.txt', str_repeat('y', 64));
        unset($phar);

        $dest = $this->work . '/out-tar-big';

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/exceeds the configured limit/');
        (new Tar())->setLimits(10_000, 8)->extract($tarPath, $dest);
    }

    public function test_extraction_fails_when_destination_cannot_be_created(): void
    {
        $zipPath = $this->work . '/ok.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'hi');
        $zip->close();

        // A regular file blocks the creation of the destination directory tree.
        $blocker = $this->work . '/blocker';
        file_put_contents($blocker, 'data');

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/Unable to create destination directory/');
        (new Zip())->extract($zipPath, $blocker . '/sub/dir');
    }

    public function test_manager_selects_extractor_by_extension(): void
    {
        $tarPath = $this->work . '/data.tar';
        $phar = new PharData($tarPath);
        $phar->addFromString('a.txt', 'A');
        unset($phar);
        $phar = new PharData($tarPath);
        $phar->compress(\Phar::GZ); // -> data.tar.gz
        unset($phar);

        $dest = $this->work . '/out-mgr';
        $this->app->make(ArchiverServiceInterface::class)->extract($this->work . '/data.tar.gz', $dest);

        $this->assertSame('A', file_get_contents($dest . '/a.txt'));
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
