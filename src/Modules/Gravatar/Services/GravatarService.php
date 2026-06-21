<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Gravatar\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Contracts\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\DataTransferObjects\GravatarResolution;

/**
 * Fluent, immutable Gravatar URL builder.
 *
 * Defaults to HTTPS. Every `set*` returns a fresh instance.
 */
class GravatarService implements GravatarServiceInterface
{
    private const HTTPS_BASE_URL = 'https://secure.gravatar.com/avatar/';

    private const HTTP_BASE_URL = 'http://www.gravatar.com/avatar/';

    private const MIN_SIZE = 1;

    private const MAX_SIZE = 2048;

    /** @var list<string> */
    private const AVAILABLE_RATINGS = ['g', 'pg', 'r', 'x'];

    /** @var list<string> */
    private const AVAILABLE_DEFAULTS = ['404', 'mp', 'identicon', 'monsterid', 'wavatar', 'retro', 'robohash', 'blank'];

    private ?string $email = null;

    private int $size = 200;

    private bool $https = true;

    private string $rating = 'g';

    private string $defaultImage = 'monsterid';

    private bool $forceDefault = false;

    private ?string $customDefaultUrl = null;

    public function setEmail(string $email): self
    {
        return $this->with(fn (self $c) => $c->email = Str::lower(trim($email)));
    }

    public function setSize(int $size): self
    {
        return $this->with(fn (self $c) => $c->size = max(self::MIN_SIZE, min(self::MAX_SIZE, abs($size))));
    }

    public function setHttps(bool $https): self
    {
        return $this->with(fn (self $c) => $c->https = $https);
    }

    public function setRating(string $rating): self
    {
        $rating = Str::lower($rating);

        if (!in_array($rating, self::AVAILABLE_RATINGS, true)) {
            throw new InvalidArgumentException(
                "Invalid rating [{$rating}]. Allowed: " . implode(', ', self::AVAILABLE_RATINGS) . '.',
            );
        }

        return $this->with(fn (self $c) => $c->rating = $rating);
    }

    public function setDefaultImage(string $defaultImage): self
    {
        if (!in_array($defaultImage, self::AVAILABLE_DEFAULTS, true)) {
            throw new InvalidArgumentException(
                "Invalid default image [{$defaultImage}]. Allowed: " . implode(', ', self::AVAILABLE_DEFAULTS) . '.',
            );
        }

        return $this->with(fn (self $c) => $c->defaultImage = $defaultImage);
    }

    public function setForceDefault(bool $forceDefault): self
    {
        return $this->with(fn (self $c) => $c->forceDefault = $forceDefault);
    }

    public function setCustomDefaultUrl(?string $customUrl): self
    {
        if ($customUrl !== null && !filter_var($customUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid custom default URL [{$customUrl}].");
        }

        return $this->with(fn (self $c) => $c->customDefaultUrl = $customUrl);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isHttps(): bool
    {
        return $this->https;
    }

    public function getRating(): string
    {
        return $this->rating;
    }

    public function getDefaultImage(): string
    {
        return $this->defaultImage;
    }

    public function isForceDefault(): bool
    {
        return $this->forceDefault;
    }

    public function getCustomDefaultUrl(): ?string
    {
        return $this->customDefaultUrl;
    }

    /** @return list<string> */
    public function availableRatings(): array
    {
        return self::AVAILABLE_RATINGS;
    }

    /** @return list<string> */
    public function availableDefaultImages(): array
    {
        return self::AVAILABLE_DEFAULTS;
    }

    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function hashEmail(string $email): string
    {
        return md5(Str::lower(trim($email)));
    }

    public function generate(): string
    {
        $email = $this->requireValidEmail();

        $baseUrl = $this->https ? self::HTTPS_BASE_URL : self::HTTP_BASE_URL;

        $params = [
            's' => $this->size,
            'r' => $this->rating,
            'd' => $this->customDefaultUrl ?? $this->defaultImage,
        ];

        if ($this->forceDefault) {
            $params['f'] = 'y';
        }

        return $baseUrl . $this->hashEmail($email) . '?' . http_build_query($params);
    }

    public function resolve(): GravatarResolution
    {
        $email = $this->requireValidEmail();

        return new GravatarResolution(
            url: $this->generate(),
            email: $email,
            hash: $this->hashEmail($email),
            size: $this->size,
            isHttps: $this->https,
            rating: $this->rating,
            defaultImage: $this->customDefaultUrl ?? $this->defaultImage,
        );
    }

    public function __toString(): string
    {
        try {
            return $this->generate();
        } catch (InvalidArgumentException) {
            return '';
        }
    }

    private function requireValidEmail(): string
    {
        if ($this->email === null || $this->email === '') {
            throw new InvalidArgumentException('An email is required to generate a Gravatar URL.');
        }

        if (!$this->isValidEmail($this->email)) {
            throw new InvalidArgumentException("Invalid email address [{$this->email}].");
        }

        return $this->email;
    }

    /**
     * Return a mutated clone, keeping the builder immutable.
     *
     * @param callable(self): mixed $mutator
     */
    private function with(callable $mutator): self
    {
        $clone = clone $this;
        $mutator($clone);

        return $clone;
    }
}
