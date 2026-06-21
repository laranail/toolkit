<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Convenience presentation helpers for common model attributes (timestamps,
 * names, content excerpts).
 *
 * These delegate straight to native Carbon / Str rather than to a formatter
 * service — the legacy `ModelFormatterService` only wrapped these same calls.
 * Anything covered by Eloquent attribute casts (date casting, etc.) is left to
 * the native cast system and is intentionally not duplicated here.
 *
 * @phpstan-require-extends Model
 */
trait HasFormatters
{
    /**
     * Default display format for date-time attributes.
     */
    protected function defaultDateTimeFormat(): string
    {
        return 'm/d/Y h:i:s a';
    }

    /**
     * Format the model's `created_at` timestamp.
     */
    public function formattedCreatedAt(?string $format = null): ?string
    {
        return $this->formatTimestamp($this->getAttribute('created_at'), $format);
    }

    /**
     * Format the model's `updated_at` timestamp.
     */
    public function formattedUpdatedAt(?string $format = null): ?string
    {
        return $this->formatTimestamp($this->getAttribute('updated_at'), $format);
    }

    /**
     * Format an arbitrary timestamp value using the model's default format.
     */
    protected function formatTimestamp(mixed $value, ?string $format = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format($format ?? $this->defaultDateTimeFormat());
    }

    /**
     * Build a full name from the model's `first_name` / `last_name` attributes.
     */
    public function formattedFullName(): string
    {
        $first = (string) ($this->getAttribute('first_name') ?? '');
        $last = (string) ($this->getAttribute('last_name') ?? '');

        return trim(Str::ucfirst($first) . ' ' . Str::ucfirst($last));
    }

    /**
     * Format the model's `username` with a leading `@`, or false when empty.
     */
    public function formattedUsername(): string|false
    {
        $username = $this->getAttribute('username');

        if (empty($username)) {
            return false;
        }

        return '@' . Str::lower((string) $username);
    }

    /**
     * Produce a truncated excerpt of the model's `content` attribute.
     */
    public function excerpt(int $length = 150): string
    {
        return Str::limit((string) ($this->getAttribute('content') ?? ''), $length);
    }
}
