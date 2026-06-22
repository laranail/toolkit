<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Utilities;

use Illuminate\Foundation\Auth\User as Authenticatable;
use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Utilities\AuthUtil;

class AuthUtilTest extends TestCase
{
    public function test_requires_a_guard_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuthUtil::for('   ');
    }

    public function test_exposes_guard_name(): void
    {
        $this->assertSame('web', AuthUtil::for('web')->guard());
    }

    public function test_unauthenticated_guard_returns_nulls(): void
    {
        $util = AuthUtil::for('web');

        $this->assertFalse($util->check());
        $this->assertNull($util->user());
        $this->assertNull($util->id());
        $this->assertNull($util->email());
    }

    public function test_authenticated_user_is_exposed(): void
    {
        $user = new AuthUtilFakeUser();
        $user->forceFill(['id' => 7, 'email' => 'jane@example.com']);

        $this->actingAs($user, 'web');

        $util = AuthUtil::for('web');

        $this->assertTrue($util->check());
        $this->assertSame(7, $util->id());
        $this->assertSame('jane@example.com', $util->email());
        $this->assertSame($user, $util->user());
    }
}

class AuthUtilFakeUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;
}
