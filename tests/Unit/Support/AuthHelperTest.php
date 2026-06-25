<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Support\AuthHelper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class AuthHelperTest extends TestCase
{
    public function test_requires_a_guard_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuthHelper::for('   ');
    }

    public function test_exposes_guard_name(): void
    {
        $this->assertSame('web', AuthHelper::for('web')->guard());
    }

    public function test_unauthenticated_guard_returns_nulls(): void
    {
        $util = AuthHelper::for('web');

        $this->assertFalse($util->check());
        $this->assertNull($util->user());
        $this->assertNull($util->id());
        $this->assertNull($util->email());
    }

    public function test_authenticated_user_is_exposed(): void
    {
        $user = new AuthHelperFakeUser();
        $user->forceFill(['id' => 7, 'email' => 'jane@example.com']);

        $this->actingAs($user, 'web');

        $util = AuthHelper::for('web');

        $this->assertTrue($util->check());
        $this->assertSame(7, $util->id());
        $this->assertSame('jane@example.com', $util->email());
        $this->assertSame($user, $util->user());
    }

    public function test_auth_helper_aliases_for_with_the_default_guard(): void
    {
        $this->assertSame('web', AuthHelper::authHelper()->guard());
        $this->assertSame('web', AuthHelper::authHelper('web')->guard());
    }

    public function test_username_falls_back_to_email(): void
    {
        $user = new AuthHelperFakeUser();
        $user->forceFill(['id' => 3, 'email' => 'jo@example.com']);

        $this->actingAs($user, 'web');

        $this->assertSame('jo@example.com', AuthHelper::for('web')->username());

        $user->forceFill(['id' => 3, 'email' => 'jo@example.com', 'username' => 'jo']);
        $this->assertSame('jo', AuthHelper::for('web')->username());
    }

    public function test_username_is_null_when_unauthenticated(): void
    {
        $this->assertNull(AuthHelper::for('web')->username());
    }

    public function test_user_exists_checks_the_guard_provider_model(): void
    {
        config()->set('auth.providers.users.model', AuthHelperDbUser::class);

        Schema::create('auth_util_users', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
        });

        AuthHelperDbUser::query()->create(['email' => 'real@example.com']);

        $util = AuthHelper::for('web');

        $this->assertTrue($util->userExists(1));
        $this->assertFalse($util->userExists(999));
        $this->assertTrue($util->userExists('real@example.com', 'email'));
        $this->assertFalse($util->userExists('ghost@example.com', 'email'));
    }
}

class AuthHelperDbUser extends Authenticatable
{
    protected $table = 'auth_util_users';

    protected $guarded = [];

    public $timestamps = false;
}

class AuthHelperFakeUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;
}
