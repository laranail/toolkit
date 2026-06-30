<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Traits;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\FilePathGuard;

class FilePathGuardHost
{
    use FilePathGuard;
}

#[Group('support')]
class FilePathGuardTest extends TestCase
{
    private FilePathGuardHost $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new FilePathGuardHost();
    }

    public function test_safe_paths_are_allowed(): void
    {
        $this->assertTrue($this->guard->isSafePath('uploads/avatar.png'));
        $this->assertTrue($this->guard->isSafePath('a/b/c.txt'));
        $this->assertSame('safe/file.txt', $this->guard->assertSafePath('safe/file.txt'));
    }

    public function test_traversal_segments_are_rejected(): void
    {
        $this->assertFalse($this->guard->isSafePath('../etc/passwd'));
        $this->assertFalse($this->guard->isSafePath('uploads/../../secret'));
        $this->assertFalse($this->guard->isSafePath('uploads\\..\\secret'));
    }

    public function test_null_bytes_are_rejected(): void
    {
        $this->assertFalse($this->guard->isSafePath("uploads/file\0.png"));
    }

    public function test_assert_safe_path_throws_on_traversal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->guard->assertSafePath('../../etc/passwd');
    }

    public function test_assert_safe_path_returns_the_path_when_safe(): void
    {
        $this->assertSame('uploads/avatar.png', $this->guard->assertSafePath('uploads/avatar.png'));
    }

    public function test_empty_and_root_relative_paths_are_safe(): void
    {
        $this->assertTrue($this->guard->isSafePath(''));
        $this->assertTrue($this->guard->isSafePath('/'));
        $this->assertTrue($this->guard->isSafePath('file.txt'));
    }

    public function test_dotdot_inside_a_filename_is_not_a_traversal_segment(): void
    {
        // `..` only counts as traversal when it is a whole path segment; a file
        // named `..foo` or `foo..bar` is safe.
        $this->assertTrue($this->guard->isSafePath('uploads/..foo.txt'));
        $this->assertTrue($this->guard->isSafePath('uploads/foo..bar'));
    }

    public function test_path_is_rejected_when_the_splitter_fails(): void
    {
        // Force PCRE to bail on the separator split so the defensive
        // `$segments === false` branch is exercised. Limits are restored
        // afterwards so no other test is affected.
        $backtrack = ini_get('pcre.backtrack_limit');
        $recursion = ini_get('pcre.recursion_limit');

        ini_set('pcre.backtrack_limit', '0');
        ini_set('pcre.recursion_limit', '0');

        try {
            $result = $this->guard->isSafePath(str_repeat('a/b', 1000));
        } finally {
            ini_set('pcre.backtrack_limit', $backtrack === false ? '1000000' : $backtrack);
            ini_set('pcre.recursion_limit', $recursion === false ? '100000' : $recursion);
        }

        $this->assertFalse($result);
    }
}
