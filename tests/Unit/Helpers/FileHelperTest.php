<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use Simtabi\Laranail\Toolkit\Helpers\FileHelper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class FileHelperTest extends TestCase
{
    public function test_format_file_size(): void
    {
        $this->assertSame('500 B', FileHelper::formatFileSize(500));
        $this->assertSame('1 KB', FileHelper::formatFileSize(1024));
        $this->assertSame('1 MB', FileHelper::formatFileSize(1024 * 1024));
        $this->assertSame('1.5 KB', FileHelper::formatFileSize(1536));
    }

    public function test_extension_and_filename(): void
    {
        $this->assertSame('png', FileHelper::extension('/var/x/Photo.PNG'));
        $this->assertSame('archive', FileHelper::filenameWithoutExtension('/tmp/archive.tar'));
    }

    public function test_is_image_uses_value_check_not_key_check(): void
    {
        // Regression: legacy Arr::has($list, $value) always returned false here.
        $this->assertTrue(FileHelper::isImage('avatar.jpg'));
        $this->assertTrue(FileHelper::isImage('LOGO.PNG'));
        $this->assertTrue(FileHelper::isImage('icon.svg'));
        $this->assertFalse(FileHelper::isImage('notes.txt'));
        $this->assertFalse(FileHelper::isImage('script.php'));
    }

    public function test_sanitize_filename_strips_separators_and_unsafe_chars(): void
    {
        $this->assertSame('etcpasswd', FileHelper::sanitizeFilename('/etc/passwd'));
        $this->assertSame('a_b.txt', FileHelper::sanitizeFilename('a b.txt'));
        $this->assertSame('evil.php', FileHelper::sanitizeFilename("evil\0.php"));
        $this->assertSame('..win.exe', FileHelper::sanitizeFilename('..\\win.exe'));
        $this->assertStringNotContainsString('/', FileHelper::sanitizeFilename('a/b/c'));
    }
}
