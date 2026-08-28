<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Http\Middleware\ApiRequestMiddleware;
use Simtabi\Laranail\Toolkit\Http\Middleware\ApiResponseMiddleware;
use Simtabi\Laranail\Toolkit\Modules\LLM\Providers\LLMServiceProvider;
use Simtabi\Laranail\Toolkit\Http\Middleware\EmailObfuscatorMiddleware;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLogMiddleware;

/**
 * Provider-level wiring: the declarative configurePackage() must merge the four
 * configs under the dotted namespace, register the route-middleware aliases and
 * child providers, and wire the custom validation rule.
 */
final class ToolkitServiceProviderTest extends TestCase
{
    public function test_every_config_merges_under_the_dotted_namespace(): void
    {
        // Three files now, not four — atlas.php left with the module.
        self::assertSame('openai', config('laranail.toolkit.llm.default_provider'));
        self::assertIsArray(config('laranail.toolkit.feature-toggles'));
        self::assertIsArray(config('laranail.toolkit.security'));
    }

    public function test_route_middleware_aliases_are_registered(): void
    {
        $aliases = $this->app->make(Router::class)->getMiddleware();

        self::assertSame(AccessLogMiddleware::class, $aliases['laranail-toolkit.access-log'] ?? null);
        self::assertSame(ApiRequestMiddleware::class, $aliases['laranail-toolkit.api-request'] ?? null);
        self::assertSame(ApiResponseMiddleware::class, $aliases['laranail-toolkit.api-response'] ?? null);
        self::assertSame(EmailObfuscatorMiddleware::class, $aliases['laranail-toolkit.email-obfuscate'] ?? null);
    }

    /**
     * The router's alias map is flat, so a second package registering
     * `api.request` does not conflict — it silently replaces this one, and the
     * damage surfaces as the wrong middleware running on a route nobody
     * touched. `access.log` and `email.obfuscate` are names an application
     * would plausibly pick for itself.
     */
    public function test_no_generic_middleware_alias_is_claimed(): void
    {
        $aliases = $this->app->make(Router::class)->getMiddleware();

        foreach (['access.log', 'api.request', 'api.response', 'email.obfuscate'] as $generic) {
            self::assertArrayNotHasKey($generic, $aliases);
        }
    }

    /**
     * A hyphen, matching the view namespace.
     *
     * A slash here is a namespace and not a path: Laravel publishes to
     * `lang/vendor/{namespace}`, so `laranail/toolkit` nests the files a
     * directory deeper than the loader looks, and every published override is
     * silently ignored while the packaged default keeps answering.
     */
    public function test_translations_are_namespaced_with_a_hyphen(): void
    {
        $namespaces = Lang::getLoader()->namespaces();

        self::assertArrayHasKey('laranail-toolkit', $namespaces);
        self::assertArrayNotHasKey('laranail/toolkit', $namespaces);
        self::assertArrayNotHasKey('toolkit', $namespaces);
    }

    public function test_child_providers_are_registered(): void
    {
        $loaded = $this->app->getLoadedProviders();

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
