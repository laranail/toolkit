<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Support\FilePathGuard;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

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
}
