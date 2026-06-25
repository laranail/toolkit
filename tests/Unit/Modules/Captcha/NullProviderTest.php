<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Captcha;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\NullProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class NullProviderTest extends TestCase
{
    public function test_always_succeeds_without_any_http(): void
    {
        Http::fake();

        $result = new NullProvider()->verify('anything');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1.0, $result->score());
        $this->assertSame('null', $result->getProviderName());
        $this->assertSame(['note' => 'no external verification'], $result->getContext());

        Http::assertNothingSent();
    }

    public function test_is_always_configured(): void
    {
        $this->assertTrue(new NullProvider()->isConfigured());
    }

    public function test_exposes_its_site_key(): void
    {
        $this->assertSame('null-site-key', new NullProvider()->getSiteKey());
        $this->assertSame('custom', new NullProvider('custom')->getSiteKey());
    }

    #[Group('security')]
    public function test_succeeds_even_for_an_empty_token(): void
    {
        Http::fake();

        $this->assertTrue(new NullProvider()->verify('')->isSuccess());

        Http::assertNothingSent();
    }
}
