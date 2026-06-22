<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

/**
 * Thrown when a UUID-related operation fails.
 */
class UuidException extends LaranailException
{
    /**
     * A required UUID value is missing.
     *
     * @param string $columnName The UUID column name.
     */
    public static function missingValue(string $columnName): self
    {
        return new self(
            message: "UUID value for [{$columnName}] is missing.",
            code: 1001,
            context: ['column' => $columnName],
        );
    }

    /**
     * A UUID value is malformed.
     *
     * @param string $value The invalid UUID value.
     */
    public static function invalidFormat(string $value): self
    {
        return new self(
            message: "Invalid UUID format: {$value}",
            code: 1002,
            context: ['value' => $value],
        );
    }

    /**
     * UUID generation failed.
     *
     * @param string $reason The failure reason.
     */
    public static function generationFailed(string $reason = 'Unknown error'): self
    {
        return new self(
            message: "UUID generation failed: {$reason}",
            code: 1003,
            context: ['reason' => $reason],
        );
    }
}
