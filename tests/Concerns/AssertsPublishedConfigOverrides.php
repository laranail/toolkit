<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Concerns;

use PHPUnit\Framework\Assert;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Test helper verifying that a PUBLISHED namespaced config override reaches its
 * dotted config key.
 *
 * The toolkit provider's override bridge runs in the REGISTER phase, so the
 * published file must exist when the provider registers. Writing it in
 * Testbench's `getEnvironmentSetUp()` races that order and is flaky; this helper
 * writes the file then registers a FRESH provider instance, which is
 * deterministic. (Toolkit-local reimplementation — the package is independent of
 * laranail/package-tools.)
 */
trait AssertsPublishedConfigOverrides
{
    /**
     * @param class-string<ServiceProvider> $providerClass
     * @param string $configKey Dotted namespaced key, e.g. 'laranail.toolkit'.
     * @param array<string, mixed> $override Values written to the published file.
     * @param string $assertKey Dotted key read after the override is applied.
     * @param mixed $expected Expected value at $assertKey.
     */
    protected function assertPublishedConfigOverride(
        string $providerClass,
        string $configKey,
        array $override,
        string $assertKey,
        mixed $expected,
    ): void {
        $published = config_path(str_replace('.', '/', $configKey) . '.php');

        try {
            File::ensureDirectoryExists(dirname($published));
            File::put($published, '<?php return ' . var_export($override, true) . ';' . PHP_EOL);

            (new $providerClass(app()))->register();

            Assert::assertSame($expected, config($assertKey), sprintf(
                'Published override at %s did not reach config(%s).',
                $published,
                $assertKey,
            ));
        } finally {
            if (File::exists($published)) {
                File::delete($published);
            }

            @rmdir(dirname($published));
        }
    }
}
