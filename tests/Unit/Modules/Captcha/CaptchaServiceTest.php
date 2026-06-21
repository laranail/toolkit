<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Captcha;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaService;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\HcaptchaProvider;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\RecaptchaProvider;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\TurnstileProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CaptchaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('laranail.toolkit.captcha.recaptcha', [
            'site_key' => 'rc-site',
            'secret_key' => 'rc-secret',
            'min_score' => 0.5,
            'timeout' => 30,
        ]);
        config()->set('laranail.toolkit.captcha.turnstile', [
            'site_key' => 'ts-site',
            'secret_key' => 'ts-secret',
            'timeout' => 30,
        ]);
        config()->set('laranail.toolkit.captcha.hcaptcha', [
            'site_key' => 'hc-site',
            'secret_key' => 'hc-secret',
            'timeout' => 30,
        ]);
    }

    public function test_config_merge_exposes_default_provider(): void
    {
        // The module provider merged the package config under laranail.toolkit.captcha.
        $this->assertNotNull(config('laranail.toolkit.captcha.default_provider'));
    }

    public function test_service_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(CaptchaService::class, $this->app->make(CaptchaService::class));
        $this->assertInstanceOf(CaptchaService::class, $this->app->make('laranail.captcha'));
    }

    public function test_default_provider_from_config_resolves_recaptcha_driver(): void
    {
        config()->set('laranail.toolkit.captcha.default_provider', 'recaptcha');

        $service = new CaptchaService((string) config('laranail.toolkit.captcha.default_provider'));

        $this->assertInstanceOf(RecaptchaProvider::class, $service->getProvider());
    }

    public function test_named_providers_resolve_to_correct_drivers(): void
    {
        $service = new CaptchaService();

        $this->assertInstanceOf(TurnstileProvider::class, $service->getProvider('turnstile'));
        $this->assertInstanceOf(HcaptchaProvider::class, $service->getProvider('hcaptcha'));
    }

    #[Group('security')]
    public function test_unknown_provider_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CaptchaService())->getProvider('Evil\\ArbitraryClass');
    }

    #[Group('security')]
    public function test_set_default_provider_rejects_unknown_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CaptchaService())->setDefaultProvider('nope');
    }

    public function test_verify_through_service_uses_selected_provider(): void
    {
        Http::fake([
            'hcaptcha.com/*' => Http::response(['success' => true], 200),
        ]);

        $result = (new CaptchaService())->verify('token', [], 'hcaptcha', '203.0.113.1');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('hcaptcha', $result->getProviderName());

        Http::assertSent(fn ($request) => $request['remoteip'] === '203.0.113.1');
    }

    #[Group('security')]
    public function test_verify_returns_failure_when_provider_unconfigured(): void
    {
        config()->set('laranail.toolkit.captcha.turnstile.secret_key', '');

        $result = (new CaptchaService())->verify('token', [], 'turnstile');

        $this->assertTrue($result->isFailure());
    }

    public function test_get_provider_names_returns_allow_list(): void
    {
        $this->assertSame(
            ['recaptcha', 'turnstile', 'hcaptcha'],
            (new CaptchaService())->getProviderNames(),
        );
    }
}
