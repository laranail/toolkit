<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Gravatar\DataTransferObjects;

/**
 * Immutable result of a Gravatar resolution.
 */
readonly class GravatarResolution
{
    public function __construct(
        public string $url,
        public string $email,
        public string $hash,
        public int $size,
        public bool $isHttps,
        public string $rating,
        public string $defaultImage,
    ) {}

    public function isSecure(): bool
    {
        return $this->isHttps;
    }

    /** Whether the rating is suitable for all audiences. */
    public function isAppropriate(): bool
    {
        return in_array($this->rating, ['g', 'pg'], true);
    }

    public function domain(): string
    {
        return $this->isHttps ? 'secure.gravatar.com' : 'www.gravatar.com';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'email' => $this->email,
            'hash' => $this->hash,
            'size' => $this->size,
            'is_https' => $this->isHttps,
            'rating' => $this->rating,
            'default_image' => $this->defaultImage,
            'domain' => $this->domain(),
            'is_appropriate' => $this->isAppropriate(),
        ];
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
