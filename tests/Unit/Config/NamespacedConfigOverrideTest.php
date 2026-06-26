<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Config;

use Simtabi\Laranail\Package\Tools\Testing\AssertsPublishedConfigOverrides;
use Simtabi\Laranail\Toolkit\Providers\ToolkitServiceProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Publishing the namespaced config writes to the nested path
 * `config/laranail/toolkit.php`; package-tools' override bridge must load that
 * back into the dotted `laranail.toolkit.*` key so the edit takes effect.
 *
 * Uses the reusable {@see AssertsPublishedConfigOverrides} helper (write file →
 * register a fresh provider → assert), which is deterministic — unlike writing
 * the file in Testbench's `getEnvironmentSetUp()`.
 */
final class NamespacedConfigOverrideTest extends TestCase
{
    use AssertsPublishedConfigOverrides;

    public function test_published_override_reaches_the_dotted_key(): void
    {
        $this->assertPublishedConfigOverride(
            ToolkitServiceProvider::class,
            'laranail.toolkit',
            ['llm' => ['default_provider' => 'gemini']],
            'laranail.toolkit.llm.default_provider',
            'gemini',
        );
    }
}
