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

    public function test_exists_size_and_last_modified_for_a_real_file(): void
    {
        $path = $this->tempFile('hello world');

        try {
            $this->assertTrue(Helper::exists($path));
            $this->assertSame(11, Helper::size($path));
            $this->assertGreaterThan(0, Helper::lastModified($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_probes_are_exception_safe_for_missing_files(): void
    {
        $missing = sys_get_temp_dir() . '/laranail-does-not-exist-' . uniqid() . '.txt';

        $this->assertFalse(Helper::exists($missing));
        $this->assertSame(0, Helper::size($missing));
        $this->assertSame(0, Helper::lastModified($missing));
        $this->assertSame([], Helper::fileInfo($missing));
    }

    public function test_probes_reject_unsafe_paths_without_throwing(): void
    {
        // Edge/bug case: a traversal segment must be rejected by FilePathGuard,
        // returning the safe defaults rather than touching the filesystem.
        $this->assertFalse(Helper::exists('/srv/app/../../etc/passwd'));
        $this->assertSame(0, Helper::size("/srv/\0/passwd"));
        $this->assertSame([], Helper::fileInfo('../secret.env'));
    }

    public function test_has_allowed_extension_is_generic_and_case_insensitive(): void
    {
        $allowed = ['sql', '.sqlite', 'DB'];

        $this->assertTrue(Helper::hasAllowedExtension('backup.SQL', $allowed));
        $this->assertTrue(Helper::hasAllowedExtension('store.sqlite', $allowed));
        $this->assertTrue(Helper::hasAllowedExtension('legacy.db', $allowed));
        $this->assertFalse(Helper::hasAllowedExtension('notes.txt', $allowed));
        $this->assertFalse(Helper::hasAllowedExtension('noext', $allowed));
    }

    public function test_file_info_reports_metadata_for_a_real_file(): void
    {
        $path = $this->tempFile('dump', '.sql');

        try {
            $info = Helper::fileInfo($path);

            $this->assertSame($path, $info['path']);
            $this->assertSame(4, $info['size']);
            $this->assertSame('sql', $info['extension']);
            $this->assertSame(pathinfo($path, PATHINFO_FILENAME), $info['name']);
            $this->assertSame(basename($path), $info['basename']);
            $this->assertGreaterThan(0, $info['last_modified']);
            $this->assertTrue($info['is_readable']);
        } finally {
            @unlink($path);
        }
    }

    private function tempFile(string $contents, string $suffix = '.txt'): string
    {
        $path = sys_get_temp_dir() . '/laranail-helper-' . uniqid() . $suffix;
        file_put_contents($path, $contents);

        return $path;
    }
}
