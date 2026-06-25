<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Support;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Support\RequirementsDiagnostics;
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

    public function test_disk_space_probe_reports_sufficient_space(): void
    {
        $result = $this->diagnostics->diskSpace([sys_get_temp_dir()], minMb: 1);

        $this->assertSame(
            ['healthy', 'warn_at_percent', 'minimum_mb', 'recommended_mb', 'paths'],
            array_keys($result),
        );
        $this->assertTrue($result['healthy']);

        $path = $result['paths'][sys_get_temp_dir()];
        $this->assertTrue($path['available']);
        $this->assertTrue($path['meets_minimum']);
        $this->assertFalse($path['warning']);
        $this->assertSame('healthy', $path['status']);
        $this->assertIsFloat($path['used_percent']);
    }

    public function test_disk_space_probe_flags_insufficient_minimum(): void
    {
        // ~1 EB free is unreachable on any test runner, so the floor must fail.
        $result = $this->diagnostics->diskSpace([sys_get_temp_dir()], minMb: 1_000_000_000_000);

        $this->assertFalse($result['healthy']);
        $this->assertFalse($result['paths'][sys_get_temp_dir()]['meets_minimum']);
        $this->assertSame('critical', $result['paths'][sys_get_temp_dir()]['status']);
    }

    public function test_disk_space_probe_warns_when_usage_exceeds_threshold(): void
    {
        // Any non-empty disk is above 0% used, so a 0% warning line trips.
        $result = $this->diagnostics->diskSpace([sys_get_temp_dir()], warnAtPercent: 0);

        $this->assertFalse($result['healthy']);
        $this->assertTrue($result['paths'][sys_get_temp_dir()]['warning']);
        $this->assertSame('warning', $result['paths'][sys_get_temp_dir()]['status']);
    }

    public function test_disk_space_probe_reports_low_below_recommendation(): void
    {
        $result = $this->diagnostics->diskSpace(
            [sys_get_temp_dir()],
            minMb: 1,
            recommendedMb: 1_000_000_000_000,
        );

        $path = $result['paths'][sys_get_temp_dir()];
        $this->assertTrue($path['meets_minimum']);
        $this->assertFalse($path['meets_recommended']);
        $this->assertSame('low', $path['status']);
        // "low" alone does not break health; only minimum / warning do.
        $this->assertTrue($result['healthy']);
    }

    public function test_disk_space_multipath_probe_degrades_on_unreadable_path(): void
    {
        $result = $this->diagnostics->diskSpace(['/this/path/does/not/exist']);

        $this->assertFalse($result['healthy']);
        $path = $result['paths']['/this/path/does/not/exist'];
        $this->assertFalse($path['available']);
        $this->assertNull($path['free']);
        $this->assertSame('unavailable', $path['status']);
    }

    public function test_disk_space_probe_handles_multiple_paths(): void
    {
        $result = $this->diagnostics->diskSpace(
            [sys_get_temp_dir(), '/this/path/does/not/exist'],
            minMb: 1,
        );

        $this->assertFalse($result['healthy']); // one path is unavailable
        $this->assertTrue($result['paths'][sys_get_temp_dir()]['available']);
        $this->assertFalse($result['paths']['/this/path/does/not/exist']['available']);
    }

    public function test_disk_space_probe_rejects_an_out_of_range_threshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->diagnostics->diskSpace([sys_get_temp_dir()], warnAtPercent: 101);
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
