<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Toolkit\Services\AuthenticationHelperService;

class AuthenticationHelperServiceTest extends TestCase
{
    public function test_fluent_setters_and_getters(): void
    {
        $service = $this->makeService($this->createMock(AuthFactory::class));

        $returned = $service
            ->setUserEmail('a@b.com')
            ->setUserId(5)
            ->setGuard('web');

        $this->assertSame($service, $returned);
        $this->assertSame('a@b.com', $service->getUserEmail());
        $this->assertSame(5, $service->getUserId());
        $this->assertSame('web', $service->getGuard());
    }

    public function test_get_user_resolves_through_the_configured_guard(): void
    {
        $user = $this->createMock(Authenticatable::class);
        $user->method('getAuthIdentifier')->willReturn(11);

        $service = $this->makeService($this->factoryReturning('api', $user, true));
        $service->setGuard('api');

        $this->assertSame($user, $service->getUser());
        $this->assertTrue($service->isAuthenticated());
        $this->assertSame(11, $service->getCurrentUserId());
    }

    public function test_guard_argument_overrides_the_default_guard(): void
    {
        $user = $this->createMock(Authenticatable::class);
        $user->method('getAuthIdentifier')->willReturn('uuid-1');

        $service = $this->makeService($this->factoryReturning('admin', $user, true));

        $this->assertSame('uuid-1', $service->getCurrentUserId('admin'));
    }

    public function test_current_user_id_is_null_when_unauthenticated(): void
    {
        $factory = $this->createMock(AuthFactory::class);
        $guard = $this->createMock(Guard::class);
        $guard->method('user')->willReturn(null);
        $factory->method('guard')->willReturn($guard);

        $service = $this->makeService($factory);

        $this->assertNull($service->getCurrentUserId());
    }

    private function makeService(AuthFactory $factory): AuthenticationHelperService
    {
        return new AuthenticationHelperService($factory);
    }

    private function factoryReturning(string $guardName, Authenticatable $user, bool $check): AuthFactory
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('user')->willReturn($user);
        $guard->method('check')->willReturn($check);

        $factory = $this->createMock(AuthFactory::class);
        $factory->method('guard')->with($guardName)->willReturn($guard);

        return $factory;
    }
}
