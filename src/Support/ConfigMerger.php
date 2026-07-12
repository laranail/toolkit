<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

/**
 * Pure array-merge strategies for configuration.
 *
 * Stateless and framework-free. `deepMerge` recurses into associative arrays
 * (later values win); for numeric/list arrays it merges index-by-index, so a
 * base list longer than the override keeps its tail — use the `append` or
 * `replace` strategy via {@see mergeWithStrategy()} when list concatenation or
 * wholesale replacement is wanted instead.
 */
final class ConfigMerger
{
    /**
     * Recursively merge two arrays, with later values taking precedence.
     *
     * @param array<int|string, mixed> $base
     * @param array<int|string, mixed> $merge
     *
     * @return array<int|string, mixed>
     */
    public function deepMerge(array $base, array $merge): array
    {
        foreach ($merge as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Replace strategy — the override wins wholesale.
     *
     * @param array<int|string, mixed> $base
     * @param array<int|string, mixed> $merge
     *
     * @return array<int|string, mixed>
     */
    public function replaceStrategy(array $base, array $merge): array
    {
        return $merge;
    }

    /**
     * Append strategy — concatenate arrays, replace scalars.
     *
     * @param array<int|string, mixed> $base
     * @param array<int|string, mixed> $merge
     *
     * @return array<int|string, mixed>
     */
    public function appendStrategy(array $base, array $merge): array
    {
        foreach ($merge as $key => $value) {
            if (isset($base[$key])) {
                $base[$key] = is_array($base[$key]) && is_array($value) ? array_merge($base[$key], $value) : $value;
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Merge using the named strategy (`deep` | `replace` | `append`).
     *
     * @param array<int|string, mixed> $base
     * @param array<int|string, mixed> $merge
     *
     * @return array<int|string, mixed>
     */
    public function mergeWithStrategy(array $base, array $merge, string $strategy = 'deep'): array
    {
        return match ($strategy) {
            'replace' => $this->replaceStrategy($base, $merge),
            'append' => $this->appendStrategy($base, $merge),
            default => $this->deepMerge($base, $merge),
        };
    }
}
