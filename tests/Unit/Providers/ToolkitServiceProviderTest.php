<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Toolkit\Http\Middleware\ApiRequestMiddleware;
use Simtabi\Laranail\Toolkit\Http\Middleware\ApiResponseMiddleware;
use Simtabi\Laranail\Toolkit\Http\Middleware\EmailObfuscatorMiddleware;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLogMiddleware;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Provider-level wiring: the declarative configurePackage() must merge the four
 * configs under the dotted namespace, register the route-middleware aliases and
 * child providers, and wire the custom validation rule.
 */
final class ToolkitServiceProviderTest extends TestCase
{
    public function test_all_four_configs_merge_under_the_dotted_namespace(): void
    {
        self::assertSame('openai', config('laranail.toolkit.llm.default_provider'));
        self::assertIsArray(config('laranail.toolkit.feature-toggles'));
        self::assertNotNull(config('laranail.toolkit.atlas.default_label'));
        self::assertNotNull(config('laranail.toolkit.captcha.default_provider'));
    }

    public function test_route_middleware_aliases_are_registered(): void
    {
        $aliases = $this->app->make(Router::class)->getMiddleware();

        self::assertSame(AccessLogMiddleware::class, $aliases['access.log'] ?? null);
        self::assertSame(ApiRequestMiddleware::class, $aliases['api.request'] ?? null);
        self::assertSame(ApiResponseMiddleware::class, $aliases['api.response'] ?? null);
        self::assertSame(EmailObfuscatorMiddleware::class, $aliases['email.obfuscate'] ?? null);
    }

    public function test_child_providers_are_registered(): void
    {
        $loaded = $this->app->getLoadedProviders();

        self::assertArrayHasKey(AtlasServiceProvider::class, $loaded);
        self::assertArrayHasKey(LLMServiceProvider::class, $loaded);
    }

    public function test_reject_common_passwords_rule_is_wired(): void
    {
        // A well-known common password fails the registered rule…
        self::assertTrue(
            Validator::make(['p' => 'password'], ['p' => 'reject_common_passwords'])->fails(),
        );
        // …while an uncommon value passes.
        self::assertFalse(
            Validator::make(['p' => 'X9$qm-Vt2!zLp7'], ['p' => 'reject_common_passwords'])->fails(),
        );
    }
}
