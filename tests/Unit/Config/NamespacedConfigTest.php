<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Config;

use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * The package config is merged (by package-tools' hasConfigFile) under the dotted
 * `laranail.toolkit.*` namespace, with each config file at its own per-file sub-key.
 */
final class NamespacedConfigTest extends TestCase
{
    public function test_main_config_merges_under_the_dotted_namespace(): void
    {
        self::assertSame('openai', config('laranail.toolkit.llm.default_provider'));
        self::assertIsArray(config('laranail.toolkit.llm'));
    }

    public function test_module_configs_get_distinct_per_file_subkeys(): void
    {
        // Was asserted against the atlas config, which moved to laranail/atlas.
        // security.php is the remaining second file, and the property under test
        // is that a second file lands on its own sub-key rather than merging
        // into the first.
        self::assertIsArray(config('laranail.toolkit.security'));
        self::assertNotSame(config('laranail.toolkit.security'), config('laranail.toolkit.llm'));
    }
}
