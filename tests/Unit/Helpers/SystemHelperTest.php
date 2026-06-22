<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use Simtabi\Laranail\Toolkit\Helpers\SystemHelper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class SystemHelperTest extends TestCase
{
    public function test_parse_memory_limit_handles_units_and_unlimited(): void
    {
        $this->assertSame(256 * 1024 * 1024, SystemHelper::parseMemoryLimit('256M'));
        $this->assertSame(1024 * 1024 * 1024, SystemHelper::parseMemoryLimit('1G'));
        $this->assertSame(512 * 1024, SystemHelper::parseMemoryLimit('512K'));
        $this->assertSame(2048, SystemHelper::parseMemoryLimit('2048'));
        $this->assertSame(-1, SystemHelper::parseMemoryLimit('-1'));
        $this->assertSame(-1, SystemHelper::parseMemoryLimit(''));
    }

    public function test_php_version_support(): void
    {
        $this->assertTrue(SystemHelper::isPhpVersionSupported('8.0.0'));
        $this->assertFalse(SystemHelper::isPhpVersionSupported('99.0.0'));
        $this->assertNotSame('', SystemHelper::phpVersion());
    }

    public function test_is_cli_is_true_under_phpunit(): void
    {
        $this->assertTrue(SystemHelper::isCli());
    }

    public function test_is_https_reads_server_state_safely(): void
    {
        unset($_SERVER['HTTPS']);
        $this->assertFalse(SystemHelper::isHttps());

        $_SERVER['HTTPS'] = 'off';
        $this->assertFalse(SystemHelper::isHttps());

        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(SystemHelper::isHttps());
        unset($_SERVER['HTTPS']);
    }

    public function test_memory_usage_and_server_env_shapes(): void
    {
        $usage = SystemHelper::memoryUsage();
        $this->assertArrayHasKey('current', $usage);
        $this->assertArrayHasKey('peak_formatted', $usage);
        $this->assertIsInt($usage['current']);

        $env = SystemHelper::serverEnv();
        $this->assertArrayHasKey('php_sapi', $env);
        $this->assertArrayHasKey('php_extensions', $env);
        $this->assertSame(PHP_VERSION, $env['php_version']);
    }
}
