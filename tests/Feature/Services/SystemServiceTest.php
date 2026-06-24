<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;
use Simtabi\Laranail\Toolkit\Services\SystemService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class SystemServiceTest extends TestCase
{
    public function test_interface_is_bound_as_a_singleton_concrete(): void
    {
        $a = $this->app->make(SystemServiceInterface::class);
        $b = $this->app->make(SystemServiceInterface::class);

        $this->assertInstanceOf(SystemService::class, $a);
        $this->assertSame($a, $b, 'SystemService should be a singleton.');
    }

    public function test_memory_usage_formatting_delegates_to_the_file_service(): void
    {
        // Swap the FileService for a spy formatter and confirm SystemService uses
        // it (single byte-formatter implementation, no duplication).
        $spy = new class() implements FileServiceInterface
        {
            public bool $called = false;

            public function formatFileSize(int $bytes, int $precision = 2): string
            {
                $this->called = true;

                return 'SPY';
            }

            public function extension(string $path): string
            {
                return '';
            }

            public function filenameWithoutExtension(string $path): string
            {
                return '';
            }

            public function isImage(string $path): bool
            {
                return false;
            }

            public function sanitizeFilename(string $filename): string
            {
                return $filename;
            }

            public function exists(string $path): bool
            {
                return false;
            }

            public function size(string $path): int
            {
                return 0;
            }

            public function lastModified(string $path): int
            {
                return 0;
            }

            public function hasAllowedExtension(string $path, array $allowed): bool
            {
                return false;
            }

            public function fileInfo(string $path): array
            {
                return [];
            }

            public function generateName(string $extension, int $length = 25): string
            {
                return '';
            }

            public function toDataUri(string $path): string
            {
                return '';
            }

            public function fromJson(string $pathOrContent): ?array
            {
                return null;
            }
        };

        $system = new SystemService($spy);
        $usage = $system->memoryUsage();

        $this->assertTrue($spy->called);
        $this->assertSame('SPY', $usage['current_formatted']);
        $this->assertSame('SPY', $usage['peak_formatted']);
    }

    public function test_parse_memory_limit_and_php_version_support(): void
    {
        $system = $this->app->make(SystemServiceInterface::class);

        $this->assertSame(256 * 1024 * 1024, $system->parseMemoryLimit('256M'));
        $this->assertTrue($system->isPhpVersionSupported('8.0.0'));
        $this->assertSame(PHP_SAPI, $system->systemInfo()['sapi']);
    }
}
