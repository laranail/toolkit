<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Closure;

/**
 * Fluent runtime configuration manager.
 *
 * Wraps the framework config repository with get/set/merge/remove/push/prepend/
 * transform helpers, file loaders, and conditional (`when`/`unless`/
 * `inEnvironment`/`whenHas`) chaining. Runtime-only — for values that must
 * persist across requests use the settings store, not this manager.
 */
interface ConfigManagerInterface
{
    public function setBasePath(string $path): static;

    public function withLogging(bool $enabled = true): static;

    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function set(string $key, mixed $value): static;

    public function override(string $key, mixed $value): static;

    public function remove(string $key): static;

    public function forget(string $key): static;

    /**
     * @param array<string, mixed> $values
     */
    public function merge(string $key, array $values): static;

    public function setIfMissing(string $key, mixed $value): static;

    /**
     * @param array<string, mixed> $values
     */
    public function setMany(array $values): static;

    /**
     * @param array<string, mixed> $values
     */
    public function overrideMany(array $values): static;

    public function loadAndOverride(string $configKey, string $filePath): static;

    public function loadPackageConfig(string $configKey, string $folder = 'config/packages'): static;

    /**
     * @param array<int, string> $configKeys
     */
    public function loadPackageConfigs(array $configKeys, string $folder = 'config/packages'): static;

    /**
     * @return array<string, mixed>
     */
    public function loadConfigFile(string $file): array;

    /**
     * @param array<string, mixed> $source
     */
    public function overrideSection(array $source, string $sectionKey, string $targetPath): static;

    public function push(string $key, mixed $value): static;

    public function prepend(string $key, mixed $value): static;

    /**
     * @param bool|Closure(): bool  $condition
     * @param Closure(static): void $callback
     */
    public function when(bool|Closure $condition, Closure $callback): static;

    /**
     * @param bool|Closure(): bool  $condition
     * @param Closure(static): void $callback
     */
    public function unless(bool|Closure $condition, Closure $callback): static;

    /**
     * @param string|array<int, string> $environments
     * @param Closure(static): void     $callback
     */
    public function inEnvironment(string|array $environments, Closure $callback): static;

    /**
     * @param Closure(static, mixed): void $callback
     */
    public function whenHas(string $key, Closure $callback): static;

    /**
     * @param Closure(mixed): mixed $transformer
     */
    public function transform(string $key, Closure $transformer): static;

    /**
     * @param Closure(mixed, array-key): mixed $callback
     */
    public function each(string $key, Closure $callback): static;

    /**
     * @return array<int, array{operation: string, key: string, value?: mixed}>
     */
    public function getLog(): array;

    public function clearLog(): static;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;
}
