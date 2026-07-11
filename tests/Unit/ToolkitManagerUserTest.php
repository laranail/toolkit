<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Simtabi\Laranail\Toolkit\Exceptions\AuthenticationException;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\ToolkitManager;

class ToolkitManagerUserTest extends TestCase
{
    private function manager(): ToolkitManager
    {
        return $this->app->make(ToolkitManager::class);
    }

    protected function defineEnvironment($app): void
    {
        // A second guard so multi-guard behaviour is exercised.
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    }

    public function test_user_is_null_when_unauthenticated(): void
    {
        $this->assertNull($this->manager()->user());
    }

    public function test_user_returns_the_authenticated_model(): void
    {
        $user = new ManagerFakeUser();
        $user->forceFill(['id' => 7, 'email' => 'jane@example.com']);
        $this->actingAs($user, 'web');

        $this->assertSame($user, $this->manager()->user());
    }

    public function test_user_is_guard_aware(): void
    {
        $user = new ManagerFakeUser();
        $user->forceFill(['id' => 1]);
        $this->actingAs($user, 'web');

        // Present on the guard we authenticated against, absent on another guard.
        $this->assertSame($user, $this->manager()->user('web'));
        $this->assertNull($this->manager()->user('admin'));
    }

    public function test_default_guard_is_configurable(): void
    {
        config()->set('laranail.toolkit.auth.default_guard', 'admin');

        $user = new ManagerFakeUser();
        $user->forceFill(['id' => 2]);
        $this->actingAs($user, 'admin');

        // No guard passed → resolves the configured default guard (admin).
        $this->assertSame($user, $this->manager()->user());
        // The web guard has no user here.
        $this->assertNull($this->manager()->user('web'));
    }

    public function test_user_as_returns_the_typed_model_or_null(): void
    {
        $user = new ManagerFakeUser();
        $user->forceFill(['id' => 3]);
        $this->actingAs($user, 'web');

        $this->assertSame($user, $this->manager()->userAs(ManagerFakeUser::class));
        // A different model class → null (never a wrong-typed instance).
        $this->assertNull($this->manager()->userAs(ManagerOtherUser::class));
    }

    public function test_user_or_fail_returns_the_user_when_authenticated(): void
    {
        $user = new ManagerFakeUser();
        $user->forceFill(['id' => 4]);
        $this->actingAs($user, 'web');

        $this->assertSame($user, $this->manager()->userOrFail());
    }

    public function test_user_or_fail_throws_when_unauthenticated(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->manager()->userOrFail();
    }

    public function test_optional_global_helper_delegates_to_the_manager(): void
    {
        require_once dirname(__DIR__, 2) . '/helpers/user.php';

        $this->assertNull(user());

        $user = new ManagerFakeUser();
        $user->forceFill(['id' => 5]);
        $this->actingAs($user, 'web');

        $this->assertSame($user, user());
    }
}

class ManagerFakeUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;
}

class ManagerOtherUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;
}
