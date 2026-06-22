<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use Exception;

/**
 * Thrown when a model is expected to expose a UUID column but none is configured.
 */
class MissingUuidColumnException extends Exception
{
    /**
     * Build the exception for a model that is missing its UUID column.
     *
     * @param class-string|string $modelClass The model class name.
     */
    public static function forModel(string $modelClass): self
    {
        return new self("No UUID column is configured on model: {$modelClass}");
    }
}
