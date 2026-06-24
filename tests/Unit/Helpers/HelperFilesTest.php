<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class HelperFilesTest extends TestCase
{
    public function test_format_file_size(): void
    {
        $this->assertSame('500 B', Helper::formatFileSize(500));
        $this->assertSame('1 KB', Helper::formatFileSize(1024));
        $this->assertSame('1 MB', Helper::formatFileSize(1024 * 1024));
        $this->assertSame('1.5 KB', Helper::formatFileSize(1536));
    }

    public function test_extension_and_filename(): void
    {
        $this->assertSame('png', Helper::extension('/var/x/Photo.PNG'));
        $this->assertSame('archive', Helper::filenameWithoutExtension('/tmp/archive.tar'));
    }

    public function test_is_image_uses_value_check_not_key_check(): void
    {
        // Regression: legacy Arr::has($list, $value) always returned false here.
        $this->assertTrue(Helper::isImage('avatar.jpg'));
        $this->assertTrue(Helper::isImage('LOGO.PNG'));
        $this->assertTrue(Helper::isImage('icon.svg'));
        $this->assertFalse(Helper::isImage('notes.txt'));
        $this->assertFalse(Helper::isImage('script.php'));
    }

    public function test_sanitize_filename_strips_separators_and_unsafe_chars(): void
    {
        $this->assertSame('etcpasswd', Helper::sanitizeFilename('/etc/passwd'));
        $this->assertSame('a_b.txt', Helper::sanitizeFilename('a b.txt'));
        $this->assertSame('evil.php', Helper::sanitizeFilename("evil\0.php"));
        $this->assertSame('..win.exe', Helper::sanitizeFilename('..\\win.exe'));
        $this->assertStringNotContainsString('/', Helper::sanitizeFilename('a/b/c'));
    }
}
