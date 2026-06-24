<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Utilities;

use Illuminate\Log\LogManager;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Enums\LogLevel;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Utilities\LoggingUtil;

class LoggingUtilTest extends TestCase
{
    public function test_log_delegates_to_the_default_logger_with_enriched_context(): void
    {
        $logs = Mockery::mock(LogManager::class);
        $logs->shouldReceive('log')->once()->withArgs(
            fn (string $level, string $message, array $context): bool => $level === 'info'
                && $message === 'hello'
                && ($context['key'] ?? null) === 'value'
                && array_key_exists('timestamp', $context)
                && array_key_exists('env', $context)
        );

        new LoggingUtil($logs)->info('hello', ['key' => 'value']);
    }

    /**
     * @return iterable<string, array{LogLevel}>
     */
    public static function levels(): iterable
    {
        yield 'debug' => [LogLevel::Debug];
        yield 'info' => [LogLevel::Info];
        yield 'warning' => [LogLevel::Warning];
        yield 'error' => [LogLevel::Error];
        yield 'critical' => [LogLevel::Critical];
    }

    #[DataProvider('levels')]
    public function test_each_level_helper_maps_to_its_psr_level(LogLevel $level): void
    {
        $logs = Mockery::mock(LogManager::class);
        $logs->shouldReceive('log')->once()->withArgs(
            fn (string $psrLevel): bool => $psrLevel === $level->value
        );

        new LoggingUtil($logs)->{$level->value}('msg');
    }

    public function test_a_named_channel_is_routed_through_channel(): void
    {
        $channelLogger = Mockery::mock(LoggerInterface::class);
        $channelLogger->shouldReceive('log')->once();

        $logs = Mockery::mock(LogManager::class);
        $logs->shouldReceive('channel')->with('slack')->once()->andReturn($channelLogger);
        $logs->shouldNotReceive('log');

        new LoggingUtil($logs)->error('boom', [], 'slack');
    }

    public function test_is_resolvable_from_the_container(): void
    {
        $this->assertInstanceOf(LoggingUtil::class, $this->app->make(LoggingUtil::class));
    }

    public function test_exception_logs_at_error_level_with_structured_context(): void
    {
        $logs = Mockery::mock(LogManager::class);
        $logs->shouldReceive('log')->once()->withArgs(
            fn (string $level, string $message, array $context): bool => $level === 'error'
                && $message === 'boom'
                && ($context['exception'] ?? null) === \RuntimeException::class
                && array_key_exists('file', $context)
                && array_key_exists('line', $context)
        );

        new LoggingUtil($logs)->exception(new \RuntimeException('boom'));
    }
}
