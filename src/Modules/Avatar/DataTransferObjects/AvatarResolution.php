<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar\DataTransferObjects;

/**
 * Immutable result of an avatar resolution operation.
 */
readonly class AvatarResolution
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $url,
        public string $sourceType,
        public string $method,
        public array $metadata = [],
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isGravatar(): bool
    {
        return $this->method === 'gravatar';
    }

    public function isInitials(): bool
    {
        return $this->method === 'initials';
    }

    public function isUrl(): bool
    {
        return $this->method === 'url';
    }

    public function isFallback(): bool
    {
        return $this->method === 'fallback';
    }

    public function isFromModel(): bool
    {
        return $this->sourceType === 'model';
    }

    public function isFromEmail(): bool
    {
        return $this->sourceType === 'email';
    }

    public function isFromName(): bool
    {
        return $this->sourceType === 'name';
    }

    public function isFromCallback(): bool
    {
        return $this->sourceType === 'callback';
    }

    /**
     * A human-readable description of how the avatar was resolved.
     */
    public function getDescription(): string
    {
        return match ($this->sourceType) {
            'model' => match ($this->method) {
                'url' => 'Stored avatar from model',
                'gravatar' => 'Gravatar for model email',
                'initials' => 'Initials avatar for model name',
                'fallback' => 'Fallback initials avatar for model',
                default => 'Avatar from model',
            },
            'email' => match ($this->method) {
                'gravatar' => 'Gravatar for email',
                'initials' => 'Initials avatar for email',
                'fallback' => 'Fallback initials avatar for email',
                default => 'Avatar for email',
            },
            'name' => 'Initials avatar for name',
            'callback' => 'Custom avatar from callback',
            default => 'Avatar',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'source_type' => $this->sourceType,
            'method' => $this->method,
            'metadata' => $this->metadata,
            'description' => $this->getDescription(),
            'is_gravatar' => $this->isGravatar(),
            'is_initials' => $this->isInitials(),
            'is_url' => $this->isUrl(),
            'is_fallback' => $this->isFallback(),
        ];
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
