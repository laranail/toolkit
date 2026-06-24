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

    // --- G8a: composer + system info ---

    public function test_is_ssl_installed_aliases_is_https(): void
    {
        unset($_SERVER['HTTPS']);
        $this->assertFalse(SystemHelper::isSslInstalled());

        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(SystemHelper::isSslInstalled());
        unset($_SERVER['HTTPS']);
    }

    public function test_composer_reads_the_application_composer_json(): void
    {
        $path = base_path('composer.json');
        $original = is_file($path) ? (string) file_get_contents($path) : null;

        try {
            file_put_contents($path, (string) json_encode([
                'name' => 'acme/app',
                'require' => ['laravel/framework' => '^13.0'],
                'require-dev' => ['phpunit/phpunit' => '^11.0'],
            ]));

            $composer = SystemHelper::composer();

            $this->assertSame('acme/app', $composer['name'] ?? null);
            $this->assertArrayHasKey('require', $composer);

            $this->assertSame('^13.0', SystemHelper::composerPackageVersion('laravel/framework'));
            $this->assertSame('^11.0', SystemHelper::composerPackageVersion('phpunit/phpunit'));
            $this->assertNull(SystemHelper::composerPackageVersion('vendor/does-not-exist'));
        } finally {
            if ($original === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $original);
            }
        }
    }

    public function test_composer_is_empty_array_when_file_is_missing(): void
    {
        $path = base_path('composer.json');
        $original = is_file($path) ? (string) file_get_contents($path) : null;

        try {
            @unlink($path);

            $this->assertSame([], SystemHelper::composer());
            $this->assertNull(SystemHelper::composerPackageVersion('laravel/framework'));
        } finally {
            if ($original !== null) {
                file_put_contents($path, $original);
            }
        }
    }

    public function test_system_info_reports_the_runtime(): void
    {
        $info = SystemHelper::systemInfo();

        $this->assertSame(PHP_VERSION, $info['php_version']);
        $this->assertSame(PHP_SAPI, $info['sapi']);
        $this->assertNotSame('', $info['laravel_version']);
        $this->assertSame('testing', $info['env']);
    }
}
