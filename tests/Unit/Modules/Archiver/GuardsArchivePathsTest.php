<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Archiver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiveException;
use Simtabi\Laranail\Toolkit\Modules\Archiver\GuardsArchivePaths;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class GuardHarness
{
    use GuardsArchivePaths;

    public function check(string $destination, string $entry): void
    {
        $this->assertWithinDestination($destination, $entry);
    }
}

#[Group('security')]
class GuardsArchivePathsTest extends TestCase
{
    private GuardHarness $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new GuardHarness();
    }

    public function test_safe_relative_entries_are_allowed(): void
    {
        $this->guard->check('/var/extract', 'file.txt');
        $this->guard->check('/var/extract', 'nested/dir/file.txt');
        $this->guard->check('/var/extract', './also/fine.txt');

        $this->assertTrue(true);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function maliciousEntries(): array
    {
        return [
            'parent traversal' => ['../evil.txt'],
            'deep traversal' => ['a/b/../../../evil.txt'],
            'absolute unix' => ['/etc/passwd'],
            'windows drive' => ['C:\\Windows\\system32'],
            'backslash traversal' => ['..\\evil.txt'],
        ];
    }

    #[DataProvider('maliciousEntries')]
    public function test_traversal_and_absolute_entries_are_refused(string $entry): void
    {
        $this->expectException(ArchiveException::class);
        $this->guard->check('/var/extract', $entry);
    }
}
