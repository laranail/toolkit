<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit;

use Simtabi\Laranail\Toolkit\Modules\Security\Passphrase;
use Simtabi\Laranail\Toolkit\Modules\Security\Password;
use Simtabi\Laranail\Toolkit\Modules\Security\Token;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\ToolkitManager;

class ToolkitManagerTest extends TestCase
{
    private function manager(): ToolkitManager
    {
        return new ToolkitManager($this->app);
    }

    public function test_token_returns_a_fresh_token_builder(): void
    {
        $manager = $this->manager();

        $this->assertInstanceOf(Token::class, $manager->token());
        // Each call hands back a fresh builder, not a shared instance.
        $this->assertNotSame($manager->token(), $manager->token());
    }

    public function test_password_returns_a_fresh_password_builder(): void
    {
        $manager = $this->manager();

        $this->assertInstanceOf(Password::class, $manager->password());
        $this->assertNotSame($manager->password(), $manager->password());
    }

    public function test_passphrase_returns_a_fresh_passphrase_builder(): void
    {
        $manager = $this->manager();

        $this->assertInstanceOf(Passphrase::class, $manager->passphrase());
        $this->assertNotSame($manager->passphrase(), $manager->passphrase());
    }
}
