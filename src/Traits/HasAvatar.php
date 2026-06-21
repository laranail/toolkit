<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Contracts\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Contracts\GravatarServiceInterface;

/**
 * Avatar helpers for Eloquent models, wired to the toolkit's Avatar and
 * Gravatar modules (resolved from the container by contract).
 *
 * The model's email and avatar attribute names are configurable via the
 * {@see self::avatarEmailAttribute()} / {@see self::avatarAttribute()} hooks
 * (defaulting to `email` / `avatar`). Attribute access is null-safe and
 * explicit — the trait never silently assumes a property exists.
 *
 * @phpstan-require-extends Model
 */
trait HasAvatar
{
    /**
     * Name of the attribute holding the e-mail address used for Gravatar.
     */
    protected function avatarEmailAttribute(): string
    {
        return 'email';
    }

    /**
     * Name of the attribute holding a stored avatar path/URL.
     */
    protected function avatarAttribute(): string
    {
        return 'avatar';
    }

    /**
     * Resolve the e-mail value, or null when unset/empty.
     */
    protected function resolveAvatarEmail(): ?string
    {
        $email = $this->getAttribute($this->avatarEmailAttribute());

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * Build a Gravatar URL for the model's e-mail, or null when no e-mail.
     */
    public function gravatar(int $size = 128, bool $isHttps = true): ?string
    {
        $email = $this->resolveAvatarEmail();

        if ($email === null) {
            return null;
        }

        return app(GravatarServiceInterface::class)
            ->setEmail($email)
            ->setSize($size)
            ->setHttps($isHttps)
            ->generate();
    }

    /**
     * Build a Gravatar URL with explicit default-image and rating overrides.
     */
    public function getGravatar(int $size = 128, string $default = 'mp', string $rating = 'g'): ?string
    {
        $email = $this->resolveAvatarEmail();

        if ($email === null) {
            return null;
        }

        return app(GravatarServiceInterface::class)
            ->setEmail($email)
            ->setSize($size)
            ->setHttps(true)
            ->setDefaultImage($default)
            ->setRating($rating)
            ->generate();
    }

    /**
     * Resolve the model's avatar, falling back to Gravatar when none is stored.
     */
    public function getAvatar(): ?string
    {
        $stored = $this->getAttribute($this->avatarAttribute());

        if (is_string($stored) && $stored !== '') {
            return $this->resolveAvatarUrl($stored);
        }

        return $this->getGravatar();
    }

    /**
     * Resolve a stored avatar path to a URL, returning the value unchanged when
     * it is not a managed storage path.
     */
    public function resolveAvatarUrl(string $path): string
    {
        if (Storage::exists($path)) {
            return Storage::url($path);
        }

        return $path;
    }

    /**
     * Generate a custom initials avatar (data URI) from the model's name.
     */
    public function generateAvatar(int $size = 128): string
    {
        return app(AvatarServiceInterface::class)
            ->setName($this->resolveAvatarName())
            ->setSize($size, $size)
            ->generateDataUri();
    }

    /**
     * Generate an avatar with a Gravatar fallback.
     */
    public function getAvatarWithFallback(int $size = 128, bool $preferGravatar = true): string
    {
        return app(AvatarServiceInterface::class)
            ->setName($this->resolveAvatarName())
            ->setSize($size, $size)
            ->generateWithGravatarFallback($size, $preferGravatar);
    }

    /**
     * Resolve a human-readable name for avatar initials.
     */
    protected function resolveAvatarName(): string
    {
        $name = $this->getAttribute('name');
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $first = $this->getAttribute('first_name');
        $last = $this->getAttribute('last_name');
        if ((is_string($first) && $first !== '') || (is_string($last) && $last !== '')) {
            return trim((string) $first . ' ' . (string) $last);
        }

        $username = $this->getAttribute('username');
        if (is_string($username) && $username !== '') {
            return $username;
        }

        return $this->resolveAvatarEmail() ?? 'User';
    }
}
