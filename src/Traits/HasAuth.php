<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationHelperServiceInterface;

/**
 * Convenience trait that delegates authentication context to a single,
 * per-instance {@see AuthenticationHelperServiceInterface} resolved from the
 * container.
 *
 * All business logic lives in the service; this trait is a thin accessor.
 */
trait HasAuth
{
    private ?AuthenticationHelperServiceInterface $authHelper = null;

    /**
     * Resolve (and memoise) the auth-helper service for this instance.
     */
    protected function authHelper(): AuthenticationHelperServiceInterface
    {
        return $this->authHelper ??= app(AuthenticationHelperServiceInterface::class);
    }

    public function setUserEmail(?string $userEmail): static
    {
        $this->authHelper()->setUserEmail($userEmail);

        return $this;
    }

    public function setUserId(int|string|null $userId): static
    {
        $this->authHelper()->setUserId($userId);

        return $this;
    }

    public function setGuard(?string $guard): static
    {
        $this->authHelper()->setGuard($guard);

        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->authHelper()->getUserEmail();
    }

    public function getUserId(): int|string|null
    {
        return $this->authHelper()->getUserId();
    }

    public function getGuard(): ?string
    {
        return $this->authHelper()->getGuard();
    }

    /**
     * Resolve the currently authenticated user.
     */
    public function getUserProperty(): Model|Authenticatable|null
    {
        return $this->authHelper()->getUser();
    }

    public function isAuthenticated(?string $guard = null): bool
    {
        return $this->authHelper()->isAuthenticated($guard);
    }
}
