<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class HelperFilesTest extends TestCase
{
    private FileServiceInterface $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = $this->app->make(FileServiceInterface::class);
    }

    public function test_format_file_size(): void
    {
        $this->assertSame('500 B', $this->files->formatFileSize(500));
        $this->assertSame('1 KB', $this->files->formatFileSize(1024));
        $this->assertSame('1 MB', $this->files->formatFileSize(1024 * 1024));
        $this->assertSame('1.5 KB', $this->files->formatFileSize(1536));
    }

    public function test_extension_and_filename(): void
    {
        $this->assertSame('png', $this->files->extension('/var/x/Photo.PNG'));
        $this->assertSame('archive', $this->files->filenameWithoutExtension('/tmp/archive.tar'));
    }

    public function test_is_image_uses_value_check_not_key_check(): void
    {
        // Regression: legacy Arr::has($list, $value) always returned false here.
        $this->assertTrue($this->files->isImage('avatar.jpg'));
        $this->assertTrue($this->files->isImage('LOGO.PNG'));
        $this->assertTrue($this->files->isImage('icon.svg'));
        $this->assertFalse($this->files->isImage('notes.txt'));
        $this->assertFalse($this->files->isImage('script.php'));
    }

    public function test_sanitize_filename_strips_separators_and_unsafe_chars(): void
    {
        $this->assertSame('etcpasswd', $this->files->sanitizeFilename('/etc/passwd'));
        $this->assertSame('a_b.txt', $this->files->sanitizeFilename('a b.txt'));
        $this->assertSame('evil.php', $this->files->sanitizeFilename("evil\0.php"));
        $this->assertSame('..win.exe', $this->files->sanitizeFilename('..\\win.exe'));
        $this->assertStringNotContainsString('/', $this->files->sanitizeFilename('a/b/c'));
    }

    public function test_exists_size_and_last_modified_for_a_real_file(): void
    {
        $path = $this->tempFile('hello world');

        try {
            $this->assertTrue($this->files->exists($path));
            $this->assertSame(11, $this->files->size($path));
            $this->assertGreaterThan(0, $this->files->lastModified($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_probes_are_exception_safe_for_missing_files(): void
    {
        $missing = sys_get_temp_dir() . '/laranail-does-not-exist-' . uniqid() . '.txt';

        $this->assertFalse($this->files->exists($missing));
        $this->assertSame(0, $this->files->size($missing));
        $this->assertSame(0, $this->files->lastModified($missing));
        $this->assertSame([], $this->files->fileInfo($missing));
    }

    public function test_probes_reject_unsafe_paths_without_throwing(): void
    {
        // Edge/bug case: a traversal segment must be rejected by FilePathGuard,
        // returning the safe defaults rather than touching the filesystem.
        $this->assertFalse($this->files->exists('/srv/app/../../etc/passwd'));
        $this->assertSame(0, $this->files->size("/srv/\0/passwd"));
        $this->assertSame([], $this->files->fileInfo('../secret.env'));
    }

    public function test_has_allowed_extension_is_generic_and_case_insensitive(): void
    {
        $allowed = ['sql', '.sqlite', 'DB'];

        $this->assertTrue($this->files->hasAllowedExtension('backup.SQL', $allowed));
        $this->assertTrue($this->files->hasAllowedExtension('store.sqlite', $allowed));
        $this->assertTrue($this->files->hasAllowedExtension('legacy.db', $allowed));
        $this->assertFalse($this->files->hasAllowedExtension('notes.txt', $allowed));
        $this->assertFalse($this->files->hasAllowedExtension('noext', $allowed));
    }

    public function test_file_info_reports_metadata_for_a_real_file(): void
    {
        $path = $this->tempFile('dump', '.sql');

        try {
            $info = $this->files->fileInfo($path);

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

    // --- Restored legacy file/util helpers ---

    public function test_generate_name_appends_a_clean_extension(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{25}\.pdf$/', $this->files->generateName('pdf'));
        // A leading/surrounding dot+whitespace on the extension is normalised.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{25}\.png$/', $this->files->generateName('  .png '));
        // An empty extension yields just the random name (no trailing dot).
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}$/', $this->files->generateName('', 10));
    }

    public function test_to_data_uri_encodes_an_existing_file(): void
    {
        $path = $this->tempFile('hello', '.txt');

        try {
            $uri = $this->files->toDataUri($path);

            $this->assertStringStartsWith('data:', $uri);
            $this->assertStringContainsString(';base64,', $uri);
            $this->assertSame('hello', base64_decode(substr($uri, (int) strpos($uri, ',') + 1)));
        } finally {
            @unlink($path);
        }
    }

    public function test_to_data_uri_returns_empty_for_missing_or_unsafe_paths(): void
    {
        $this->assertSame('', $this->files->toDataUri(sys_get_temp_dir() . '/laranail-missing-' . uniqid()));
        $this->assertSame('', $this->files->toDataUri('../../etc/passwd'));
    }

    public function test_from_json_reads_a_file_or_a_raw_string(): void
    {
        $this->assertSame(['a' => 1, 'b' => 2], $this->files->fromJson('{"a":1,"b":2}'));

        $path = $this->tempFile('{"x":true}', '.json');

        try {
            $this->assertSame(['x' => true], $this->files->fromJson($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_from_json_returns_null_for_invalid_json(): void
    {
        $this->assertNull($this->files->fromJson('not json'));
        // A scalar JSON value is not an array, so it is rejected.
        $this->assertNull($this->files->fromJson('42'));
    }

    private function tempFile(string $contents, string $suffix = '.txt'): string
    {
        $path = sys_get_temp_dir() . '/laranail-helper-' . uniqid() . $suffix;
        file_put_contents($path, $contents);

        return $path;
    }
}
