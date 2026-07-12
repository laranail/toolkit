<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use Simtabi\Laranail\Toolkit\Services\PythonApiService;

/**
 * Failures raised by {@see PythonApiService}.
 */
final class PythonApiException extends LaranailException
{
    public static function unknownService(string $name): self
    {
        return new self(
            "Unknown Python API service '{$name}'. Define it under config('laranail.toolkit.python.services.{$name}').",
            context: ['service' => $name],
        );
    }

    public static function missingBaseUrl(string $name): self
    {
        return new self(
            "Python API service '{$name}' has no base_url configured.",
            context: ['service' => $name],
        );
    }
}
