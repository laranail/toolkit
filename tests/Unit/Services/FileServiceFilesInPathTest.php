<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;

final class FileServiceFilesInPathTest extends TestCase
{
    private string $sandbox;

    private FileServiceInterface $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/laranail-files-' . bin2hex(random_bytes(6));
        mkdir($this->sandbox . '/nested', 0o755, true);

        file_put_contents($this->sandbox . '/b.txt', 'b');
        file_put_contents($this->sandbox . '/a.txt', 'a');
        file_put_contents($this->sandbox . '/nested/c.txt', 'c');

        $this->files = $this->app->make(FileServiceInterface::class);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->sandbox));

        parent::tearDown();
    }

    public function test_it_lists_one_level_by_default(): void
    {
        self::assertSame(['a.txt', 'b.txt'], $this->files->filesInPath($this->sandbox));
    }

    public function test_it_descends_when_asked(): void
    {
        self::assertSame(
            ['a.txt', 'b.txt', 'nested/c.txt'],
            $this->files->filesInPath($this->sandbox, recursive: true),
        );
    }

    public function test_the_results_are_relative_to_the_directory(): void
    {
        foreach ($this->files->filesInPath($this->sandbox, true) as $path) {
            self::assertStringNotContainsString($this->sandbox, $path);
        }
    }

    public function test_a_missing_directory_is_empty_rather_than_an_error(): void
    {
        self::assertSame([], $this->files->filesInPath($this->sandbox . '/nope'));
    }

    public function test_a_traversal_path_is_refused(): void
    {
        self::assertSame([], $this->files->filesInPath($this->sandbox . '/../../etc'));
    }

    public function test_an_empty_directory_is_empty(): void
    {
        mkdir($this->sandbox . '/blank');

        self::assertSame([], $this->files->filesInPath($this->sandbox . '/blank'));
    }
}
