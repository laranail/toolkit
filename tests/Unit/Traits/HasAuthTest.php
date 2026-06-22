<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Traits;

use Simtabi\Laranail\Toolkit\Services\AuthenticationHelperService;
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationHelperServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasAuth;

/**
 * Test double exercising the {@see HasAuth} trait.
 */
class HasAuthFixture
{
    use HasAuth;
}

class HasAuthTest extends TestCase
{
    public function test_interface_is_bound_to_the_concrete_service(): void
    {
        $this->assertInstanceOf(
            AuthenticationHelperService::class,
            app(AuthenticationHelperServiceInterface::class),
        );
    }

    public function test_trait_delegates_user_context_to_a_memoised_service(): void
    {
        $object = new HasAuthFixture();

        $object->setUserEmail('a@b.com')->setUserId(9)->setGuard('web');

        $this->assertSame('a@b.com', $object->getUserEmail());
        $this->assertSame(9, $object->getUserId());
        $this->assertSame('web', $object->getGuard());
    }

    public function test_is_authenticated_reflects_the_guard_state(): void
    {
        $object = new HasAuthFixture();

        // No user is logged in under the default guard in the test harness.
        $this->assertFalse($object->isAuthenticated());
        $this->assertNull($object->getUserProperty());
    }
}
