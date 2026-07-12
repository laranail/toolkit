<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use Simtabi\Laranail\Toolkit\Services\ConfigManager;

/**
 * Configuration-management failures raised by
 * {@see ConfigManager}.
 *
 * Static named constructors carry rich context (the offending path) on the
 * structured {@see LaranailException} base.
 */
final class ConfigException extends LaranailException
{
    public static function fileNotFound(string $path): self
    {
        return new self(
            "Config file not found: {$path}",
            context: ['path' => $path],
        );
    }

    public static function notAnArray(string $path): self
    {
        return new self(
            "Config file did not return an array: {$path}",
            context: ['path' => $path],
        );
    }
}
