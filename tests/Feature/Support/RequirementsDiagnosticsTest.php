<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Support;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Support\Diagnostics\RequirementsDiagnostics;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('support')]
class RequirementsDiagnosticsTest extends TestCase
{
    private RequirementsDiagnostics $diagnostics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diagnostics = new RequirementsDiagnostics();
    }

    public function test_php_version_check_reports_support(): void
    {
        $result = $this->diagnostics->checkPhpVersion();

        $this->assertSame(['current', 'minimum', 'supported'], array_keys($result));
        $this->assertTrue($result['supported']);
    }

    public function test_php_version_check_flags_an_impossible_floor(): void
    {
        $result = $this->diagnostics->checkPhpVersion('99.0.0');

        $this->assertFalse($result['supported']);
    }

    public function test_extension_check_returns_a_boolean_per_extension(): void
    {
        $result = $this->diagnostics->checkExtensions(['json', 'mbstring']);

        $this->assertArrayHasKey('json', $result);
        $this->assertTrue($result['json']);
    }

    public function test_writable_directory_check(): void
    {
        $this->assertTrue($this->diagnostics->isDirectoryWritable(sys_get_temp_dir()));
        $this->assertFalse($this->diagnostics->isDirectoryWritable('/this/path/does/not/exist/and/is/not/writable'));
    }

    public function test_missing_extensions_lists_only_absent_ones(): void
    {
        $this->assertSame([], $this->diagnostics->missingExtensions(['json', 'mbstring']));
        $this->assertSame(
            ['definitely_not_a_real_extension'],
            $this->diagnostics->missingExtensions(['json', 'definitely_not_a_real_extension']),
        );
    }

    public function test_disk_space_probe_reports_free_space(): void
    {
        $result = $this->diagnostics->checkDiskSpace(sys_get_temp_dir());

        $this->assertSame(
            ['path', 'free', 'total', 'minimum', 'available', 'sufficient'],
            array_keys($result),
        );
        $this->assertTrue($result['available']);
        $this->assertIsInt($result['free']);
        $this->assertTrue($result['sufficient']); // no minimum given
    }

    public function test_disk_space_probe_flags_an_impossible_minimum(): void
    {
        $result = $this->diagnostics->checkDiskSpace(sys_get_temp_dir(), PHP_INT_MAX);

        $this->assertTrue($result['available']);
        $this->assertFalse($result['sufficient']);
    }

    public function test_disk_space_probe_degrades_on_unreadable_path(): void
    {
        $result = $this->diagnostics->checkDiskSpace('/this/path/does/not/exist');

        $this->assertFalse($result['available']);
        $this->assertNull($result['free']);
        $this->assertFalse($result['sufficient']);
    }

    public function test_about_array_exposes_expected_keys(): void
    {
        $about = $this->diagnostics->toAboutArray();

        $this->assertArrayHasKey('PHP Version', $about);
        $this->assertArrayHasKey('Minimum PHP', $about);
        $this->assertArrayHasKey('Required Extensions', $about);
        $this->assertArrayHasKey('Storage Writable', $about);
        $this->assertArrayHasKey('Storage Free Space', $about);
    }

    public function test_about_command_registration_does_not_error(): void
    {
        // Booting the package provider already invokes AboutCommand::add();
        // running `about` must not throw.
        $this->artisan('about', ['--only' => 'laranail-toolkit'])->assertExitCode(0);
    }
}
