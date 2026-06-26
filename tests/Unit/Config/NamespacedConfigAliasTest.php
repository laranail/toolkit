<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Config;

use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * The published config is keyed flat (config/laranail-toolkit*.php → config key
 * `laranail-toolkit*`) so overrides work, but the package also exposes the dotted
 * `laranail.toolkit.*` namespace. Both forms must resolve to the same values.
 */
final class NamespacedConfigAliasTest extends TestCase
{
    public function test_main_config_resolves_under_both_flat_and_namespaced_keys(): void
    {
        $flat = config('laranail-toolkit.llm.default_provider');
        $namespaced = config('laranail.toolkit.llm.default_provider');

        self::assertNotNull($flat);
        self::assertSame($flat, $namespaced);
    }

    public function test_nested_section_resolves_under_namespaced_key(): void
    {
        self::assertSame(
            config('laranail-toolkit.llm'),
            config('laranail.toolkit.llm'),
        );
    }

    public function test_deferred_captcha_module_also_aliases_when_resolved(): void
    {
        // Resolving the service triggers the deferred provider, which sets the alias.
        $this->app->make(CaptchaService::class);

        self::assertSame(
            config('laranail-toolkit-captcha.default_provider'),
            config('laranail.toolkit.captcha.default_provider'),
        );
    }

    public function test_flat_key_remains_canonical_for_publishing(): void
    {
        // The flat key is what config/laranail-toolkit.php publishes to and what
        // the package reads internally — it must stay populated.
        self::assertIsArray(config('laranail-toolkit'));
        self::assertArrayHasKey('llm', (array) config('laranail-toolkit'));
    }
}
