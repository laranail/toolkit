<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Simtabi\Laranail\Toolkit\Services\ValidationService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class ValidationServiceSecurityTest extends TestCase
{
    private function service(): ValidationService
    {
        return new ValidationService($this->app->make('session.store'), new NullLogger());
    }

    public function test_xss_in_the_validation_message_is_escaped(): void
    {
        // A validation message echoing attacker-controlled input.
        Session::put('errors', new MessageBag([
            'name' => ['The value <script>alert(1)</script> is invalid.'],
        ]));

        $html = (string) $this->service()->getErrorBagMessage('name');

        // The raw script tag must NOT appear; its escaped form must.
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_xss_in_caller_supplied_css_classes_is_escaped(): void
    {
        Session::put('errors', new MessageBag(['name' => ['bad']]));

        $html = (string) $this->service()->getErrorBagMessage(
            'name',
            errorMsgClass: '"><script>alert(1)</script>',
            wrapperClass: '"onmouseover="alert(2)',
        );

        // No attribute-breakout: the injected quote/markup is escaped.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('"onmouseover="', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function test_no_raw_user_data_survives_in_rendered_html(): void
    {
        // Field key itself is never reflected into the HTML, but a hostile
        // message must be fully neutralised.
        Session::put('errors', new MessageBag([
            'email' => ['<img src=x onerror=alert(1)>'],
        ]));

        $html = (string) $this->service()->getErrorBagMessage('email');

        // The angle brackets are escaped, so no live <img> tag exists in the DOM.
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }
}
