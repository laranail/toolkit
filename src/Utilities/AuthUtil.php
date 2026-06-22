<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Utilities;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use InvalidArgumentException;

/**
 * Typed accessor for a single named guard.
 *
 * Named `AuthUtil` (not `Auth`) to avoid colliding with the framework's `Auth`
 * facade. It wraps the resolved {@see Guard} so callers get a typed `user()` /
 * `id()` / `email()` surface for one guard without re-resolving it each call.
 */
final readonly class AuthUtil
{
    private function __construct(
        private string $guard,
        private Guard $auth,
    ) {}

    /**
     * Build an accessor for the given guard.
     *
     * @throws InvalidArgumentException when no guard name is supplied
     */
    public static function for(string $guard, ?AuthFactory $factory = null): self
    {
        if (trim($guard) === '') {
            throw new InvalidArgumentException('A guard name is required.');
        }

        $factory ??= app(AuthFactory::class);

        return new self($guard, $factory->guard($guard));
    }

    /**
     * The guard name this accessor is bound to.
     */
    public function guard(): string
    {
        return $this->guard;
    }

    /**
     * The underlying guard instance.
     */
    public function auth(): Guard
    {
        return $this->auth;
    }

    /**
     * The currently authenticated user for this guard, if any.
     */
    public function user(): ?Authenticatable
    {
        return $this->auth->user();
    }

    /**
     * The authenticated user's identifier, or null when unauthenticated.
     */
    public function id(): int|string|null
    {
        return $this->auth->id();
    }

    /**
     * The authenticated user's `email` attribute, or null when unavailable.
     */
    public function email(): ?string
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        $email = data_get($user, 'email');

        return $email === null ? null : (string) $email;
    }

    /**
     * Whether a user is authenticated for this guard.
     */
    public function check(): bool
    {
        return $this->auth->check();
    }
}
