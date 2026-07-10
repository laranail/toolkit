<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Barryvdh\Debugbar\Facades\Debugbar;
use Clockwork\Support\Laravel\ClockworkServiceProvider;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;
use Simtabi\Laranail\Toolkit\Services\SystemService;

/**
 * Chainable API for adjusting PHP runtime settings during heavy operations:
 * memory limits, execution time, arbitrary INI directives, and disabling
 * debugging tools (Telescope, Xdebug, Clockwork, Debugbar).
 *
 * Original values are captured on construction; {@see apply()} materialises the
 * pending changes, {@see restore()} puts them back, and {@see scope()} does both
 * around a callback:
 *
 * ```php
 * RuntimeConfigurator::forBatchProcessing()->scope(fn () => $import->run());
 * ```
 *
 * Apply/restore are symmetric — Xdebug is re-enabled on restore, and restore
 * reuses the same `set_time_limit` / `error_reporting` special-casing as apply.
 * Memory *introspection* (usage, peak, formatted sizes) is intentionally NOT
 * duplicated here — read it from
 * {@see SystemServiceInterface}
 * (`Toolkit::system()->memoryUsage()`).
 *
 * @api
 */
final class RuntimeConfigurator
{
    private const string XDEBUG_MODE = 'xdebug.mode';

    /** @var array<string, mixed> Original values for restoration. */
    private array $originalValues = [];

    /** @var array<string, mixed> Pending INI changes to apply. */
    private array $pending = [];

    /** @var array<string, bool> Debugging tools to disable. */
    private array $disableTools = [
        'telescope' => false,
        'xdebug' => false,
        'clockwork' => false,
        'debugbar' => false,
    ];

    private bool $logging = false;

    private ?string $logChannel = null;

    private bool $applied = false;

    public function __construct()
    {
        $this->captureOriginalValues();
    }

    public static function make(): self
    {
        return new self();
    }

    /** Pre-configured for queue jobs: 1G memory, no timeout, Telescope disabled. */
    public static function forQueueJob(): self
    {
        return self::make()->memory('1G')->timeout(0)->disableTelescope();
    }

    /** Pre-configured for heavy batch operations: 2G memory, no timeout, all debugging off. */
    public static function forBatchProcessing(): self
    {
        return self::make()->memory('2G')->timeout(0)->disableAllDebugging();
    }

    /** Pre-configured for imports: 1G memory, 30-minute timeout, Telescope disabled. */
    public static function forImport(): self
    {
        return self::make()->memory('1G')->timeoutMinutes(30)->disableTelescope();
    }

    /** Pre-configured for exports: 1G memory, 15-minute timeout, Telescope disabled. */
    public static function forExport(): self
    {
        return self::make()->memory('1G')->timeoutMinutes(15)->disableTelescope();
    }

    // --- Memory -------------------------------------------------------------

    public function memory(string $limit): self
    {
        $this->pending['memory_limit'] = $limit;

        return $this;
    }

    public function memoryMb(int $megabytes): self
    {
        return $this->memory("{$megabytes}M");
    }

    public function memoryGb(int|float $gigabytes): self
    {
        return $this->memory(((int) ($gigabytes * 1024)) . 'M');
    }

    public function unlimitedMemory(): self
    {
        return $this->memory('-1');
    }

    // --- Execution time -----------------------------------------------------

    public function timeout(int $seconds): self
    {
        $this->pending['max_execution_time'] = $seconds;

        return $this;
    }

    public function maxExecutionTime(int $seconds): self
    {
        return $this->timeout($seconds);
    }

    public function noTimeout(): self
    {
        return $this->timeout(0);
    }

    public function timeoutMinutes(int $minutes): self
    {
        return $this->timeout($minutes * 60);
    }

    public function timeoutHours(int $hours): self
    {
        return $this->timeout($hours * 3600);
    }

    // --- Error reporting ----------------------------------------------------

    public function errorReporting(int $level): self
    {
        $this->pending['error_reporting'] = $level;

        return $this;
    }

    public function reportAllErrors(): self
    {
        return $this->errorReporting(E_ALL);
    }

    public function reportErrorsOnly(): self
    {
        return $this->errorReporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
    }

    public function suppressErrors(): self
    {
        return $this->errorReporting(0);
    }

    public function displayErrors(bool $display = true): self
    {
        $this->pending['display_errors'] = $display ? '1' : '0';

        return $this;
    }

    // --- Debugging tools ----------------------------------------------------

    public function disableTelescope(): self
    {
        $this->disableTools['telescope'] = true;

        return $this;
    }

    public function enableTelescope(): self
    {
        $this->disableTools['telescope'] = false;

        return $this;
    }

    public function disableXdebug(): self
    {
        $this->disableTools['xdebug'] = true;

        return $this;
    }

    public function enableXdebug(): self
    {
        $this->disableTools['xdebug'] = false;

        return $this;
    }

    public function disableClockwork(): self
    {
        $this->disableTools['clockwork'] = true;

        return $this;
    }

    public function enableClockwork(): self
    {
        $this->disableTools['clockwork'] = false;

        return $this;
    }

    public function disableDebugbar(): self
    {
        $this->disableTools['debugbar'] = true;

        return $this;
    }

    public function enableDebugbar(): self
    {
        $this->disableTools['debugbar'] = false;

        return $this;
    }

    public function disableAllDebugging(): self
    {
        return $this->disableTelescope()->disableXdebug()->disableClockwork()->disableDebugbar();
    }

    // --- Arbitrary INI ------------------------------------------------------

    public function set(string $key, mixed $value): self
    {
        $this->pending[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function setMany(array $settings): self
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function realpathCacheSize(string $size): self
    {
        return $this->set('realpath_cache_size', $size);
    }

    public function realpathCacheTtl(int $seconds): self
    {
        return $this->set('realpath_cache_ttl', $seconds);
    }

    public function postMaxSize(string $size): self
    {
        return $this->set('post_max_size', $size);
    }

    public function uploadMaxFilesize(string $size): self
    {
        return $this->set('upload_max_filesize', $size);
    }

    public function forLargeUploads(string $maxSize = '100M'): self
    {
        return $this->postMaxSize($maxSize)->uploadMaxFilesize($maxSize)->memory('512M')->timeoutMinutes(10);
    }

    // --- Conditional --------------------------------------------------------

    public function when(bool $condition, callable $callback, ?callable $else = null): self
    {
        if ($condition) {
            $callback($this);
        } elseif ($else !== null) {
            $else($this);
        }

        return $this;
    }

    public function unless(bool $condition, callable $callback): self
    {
        return $this->when(!$condition, $callback);
    }

    public function whenCli(callable $callback): self
    {
        return $this->when(self::isCli(), $callback);
    }

    public function whenWeb(callable $callback): self
    {
        return $this->when(!self::isCli(), $callback);
    }

    // --- Logging ------------------------------------------------------------

    public function withLogging(?string $channel = null): self
    {
        $this->logging = true;
        $this->logChannel = $channel;

        return $this;
    }

    public function withoutLogging(): self
    {
        $this->logging = false;

        return $this;
    }

    // --- Apply / restore / scope -------------------------------------------

    /**
     * Materialise the pending INI + debug-tool settings. Idempotent: a second
     * call while already applied is a no-op (call {@see restore()} first).
     */
    public function apply(): self
    {
        if ($this->applied) {
            return $this;
        }

        foreach ($this->pending as $key => $value) {
            $this->applyIniSetting($key, $value);
        }

        $this->applyDebuggingToolSettings();
        $this->applied = true;

        if ($this->logging) {
            $this->logChanges('Applied runtime configuration');
        }

        return $this;
    }

    /**
     * Apply, run the callback, and restore in a `finally` (returns the callback
     * result).
     */
    public function scope(callable $callback): mixed
    {
        $this->apply();

        try {
            return $callback();
        } finally {
            $this->restore();
        }
    }

    /**
     * Restore the captured original values, reusing the same special-casing as
     * {@see apply()} (so `max_execution_time` / `error_reporting` restore
     * correctly), and re-enable every disabled debugging tool.
     */
    public function restore(): self
    {
        foreach ($this->originalValues as $key => $value) {
            if ($value === false || $key === self::XDEBUG_MODE) {
                continue;
            }

            $this->writeIniSetting($key, $value);
        }

        $this->restoreDebuggingTools();
        $this->applied = false;

        if ($this->logging) {
            $this->logChanges('Restored runtime configuration');
        }

        return $this;
    }

    // --- Accessors ----------------------------------------------------------

    public function isApplied(): bool
    {
        return $this->applied;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPending(): array
    {
        return $this->pending;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginal(): array
    {
        return $this->originalValues;
    }

    /**
     * @return array<string, bool>
     */
    public function getDisabledTools(): array
    {
        return array_filter($this->disableTools);
    }

    public function get(string $key): string|false
    {
        return ini_get($key);
    }

    // --- Static helpers -----------------------------------------------------

    public static function setMemory(string $limit): void
    {
        self::make()->memory($limit)->apply();
    }

    public static function setTimeout(int $seconds): void
    {
        self::make()->timeout($seconds)->apply();
    }

    /**
     * Whether PHP is running under a CLI SAPI (`cli` or `phpdbg`). Matches
     * {@see SystemService::isCli()}.
     */
    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    public static function hasTelescopeInstalled(): bool
    {
        return class_exists(Telescope::class);
    }

    public static function hasXdebugLoaded(): bool
    {
        return extension_loaded('xdebug');
    }

    public static function hasDebugbarInstalled(): bool
    {
        return class_exists(Debugbar::class);
    }

    public static function hasClockworkInstalled(): bool
    {
        return class_exists(ClockworkServiceProvider::class);
    }

    // --- Internal -----------------------------------------------------------

    private function captureOriginalValues(): void
    {
        foreach ([
            'memory_limit', 'max_execution_time', 'error_reporting',
            'display_errors', 'realpath_cache_size', 'realpath_cache_ttl',
            'post_max_size', 'upload_max_filesize',
        ] as $key) {
            $this->originalValues[$key] = ini_get($key);
        }
    }

    private function applyIniSetting(string $key, mixed $value): void
    {
        if (!array_key_exists($key, $this->originalValues)) {
            $this->originalValues[$key] = ini_get($key);
        }

        $this->writeIniSetting($key, $value);
    }

    /**
     * Write a single INI directive, honouring the settings that need a
     * dedicated function rather than `ini_set`.
     */
    private function writeIniSetting(string $key, mixed $value): void
    {
        if ($key === 'max_execution_time' && function_exists('set_time_limit')) {
            @set_time_limit(Cast::toInt($value));

            return;
        }

        if ($key === 'error_reporting') {
            error_reporting(Cast::toInt($value));

            return;
        }

        @ini_set($key, Cast::toString($value));
    }

    private function applyDebuggingToolSettings(): void
    {
        if ($this->disableTools['telescope'] && class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        if ($this->disableTools['xdebug'] && extension_loaded('xdebug')) {
            // Capture the current mode once so restore() can put it back.
            if (!array_key_exists(self::XDEBUG_MODE, $this->originalValues)) {
                $this->originalValues[self::XDEBUG_MODE] = ini_get(self::XDEBUG_MODE);
            }
            if (function_exists('xdebug_disable')) {
                @xdebug_disable();
            }
            @ini_set(self::XDEBUG_MODE, 'off');
        }

        if ($this->disableTools['debugbar'] && class_exists(Debugbar::class)) {
            Debugbar::disable();
        }

        if ($this->disableTools['clockwork'] && class_exists(ClockworkServiceProvider::class)) {
            config(['clockwork.enable' => false]);
        }
    }

    private function restoreDebuggingTools(): void
    {
        if ($this->disableTools['telescope'] && class_exists(Telescope::class)) {
            Telescope::startRecording();
        }

        if ($this->disableTools['xdebug'] && extension_loaded('xdebug')) {
            $originalMode = $this->originalValues[self::XDEBUG_MODE] ?? false;
            if (is_string($originalMode)) {
                @ini_set(self::XDEBUG_MODE, $originalMode);
            }
            if (function_exists('xdebug_enable')) {
                @xdebug_enable();
            }
        }

        if ($this->disableTools['debugbar'] && class_exists(Debugbar::class)) {
            Debugbar::enable();
        }

        if ($this->disableTools['clockwork'] && class_exists(ClockworkServiceProvider::class)) {
            config(['clockwork.enable' => true]);
        }
    }

    private function logChanges(string $message): void
    {
        $context = [
            'pending' => $this->pending,
            'disabled_tools' => array_filter($this->disableTools),
            'memory_usage_bytes' => memory_get_usage(true),
        ];

        if ($this->logChannel !== null) {
            Log::channel($this->logChannel)->debug($message, $context);
        } else {
            Log::debug($message, $context);
        }
    }
}
