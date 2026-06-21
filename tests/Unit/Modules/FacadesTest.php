<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules;

use Simtabi\Laranail\Toolkit\Modules\Avatar\Contracts\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Facades\Avatar;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Facades\Captcha;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Services\CaptchaService;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Contracts\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Facades\Gravatar;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Facades\Notifications;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Services\NotificationService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class FacadesTest extends TestCase
{
    public function test_avatar_facade_resolves_its_root(): void
    {
        $this->assertInstanceOf(AvatarServiceInterface::class, Avatar::getFacadeRoot());
    }

    public function test_gravatar_facade_resolves_its_root(): void
    {
        $this->assertInstanceOf(GravatarServiceInterface::class, Gravatar::getFacadeRoot());
    }

    public function test_captcha_facade_resolves_its_root(): void
    {
        $this->assertInstanceOf(CaptchaService::class, Captcha::getFacadeRoot());
    }

    public function test_notifications_facade_resolves_its_root(): void
    {
        $this->assertInstanceOf(NotificationService::class, Notifications::getFacadeRoot());
    }

    public function test_gravatar_facade_proxies_a_method_call(): void
    {
        $url = Gravatar::setEmail('user@example.com')->setSize(120)->generate();

        $this->assertStringContainsString('gravatar.com/avatar/', $url);
    }
}
