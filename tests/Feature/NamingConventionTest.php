<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature;

use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Package\Tools\Testing\NamingScope;
use Simtabi\Laranail\Package\Tools\Testing\NameRegistry;
use Simtabi\Laranail\Toolkit\Providers\ToolkitServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\AssertsRegisteredNames;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLog;

/**
 * Every name the toolkit registers into a flat, host-owned registry carries the
 * vendor and the package slug, read from the live registries of the booted
 * application rather than from the provider's source.
 *
 * `laranail.archiver`, `laranail.livewire` and `laranail.llm` are module
 * container prefixes accepted as vendor-scoped exceptions (D2 in the estate
 * follow-ups plan); they are passed as sanctioned prefixes, not renamed.
 */
final class NamingConventionTest extends TestCase
{
    use AssertsRegisteredNames;

    public function test_the_view_namespace_is_registered_in_both_spellings(): void
    {
        $names = $this->assertViewNamespacesScoped($this->scope(basePath: $this->packagePath('resources')), atLeast: 2);

        self::assertContains('laranail/toolkit', $names);
        self::assertContains('laranail-toolkit', $names);
    }

    public function test_the_translation_namespace_is_registered_in_both_spellings(): void
    {
        $names = $this->assertTranslationNamespacesScoped($this->scope(basePath: $this->packagePath('resources')), atLeast: 2);

        self::assertContains('laranail/toolkit', $names);
        self::assertContains('laranail-toolkit', $names);
    }

    public function test_both_view_spellings_find_the_same_file(): void
    {
        self::assertTrue(view()->exists('laranail/toolkit::blade-javascript'));
        self::assertTrue(view()->exists('laranail-toolkit::blade-javascript'));
    }

    public function test_the_middleware_aliases_are_scoped(): void
    {
        $this->assertMiddlewareAliasesScoped($this->scope(), atLeast: 4);
    }

    public function test_the_container_keys_are_scoped_apart_from_the_deprecated_bare_ones(): void
    {
        $names = $this->assertContainerAliasesScoped(
            $this->scope(basePath: $this->packagePath('src')),
            deprecated: ['AccessLog', 'helper'],
        );

        self::assertContains('laranail.toolkit.access-log', $names);
        self::assertContains('laranail.toolkit.helper', $names);
    }

    public function test_the_scoped_container_keys_keep_their_lifetimes(): void
    {
        self::assertInstanceOf(AccessLog::class, $this->app->make('laranail.toolkit.access-log'));
        self::assertNotSame($this->app->make('laranail.toolkit.access-log'), $this->app->make('laranail.toolkit.access-log'));

        self::assertInstanceOf(Helper::class, $this->app->make('laranail.toolkit.helper'));
        self::assertSame($this->app->make('laranail.toolkit.helper'), $this->app->make('laranail.toolkit.helper'));
    }

    public function test_the_bare_container_keys_still_resolve_and_warn_once_each(): void
    {
        ToolkitServiceProvider::resetDeprecationNotices();

        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $helper = $this->app->make('helper');
            $this->app->make('helper');
            $accessLog = $this->app->make('AccessLog');
            $this->app->make('AccessLog');
        } finally {
            restore_error_handler();
        }

        self::assertSame($this->app->make('laranail.toolkit.helper'), $helper);
        self::assertInstanceOf(AccessLog::class, $accessLog);
        self::assertCount(2, $notices);
        self::assertStringContainsString('[helper]', $notices[0]);
        self::assertStringContainsString('[laranail.toolkit.helper]', $notices[0]);
        self::assertStringContainsString('[AccessLog]', $notices[1]);
        self::assertStringContainsString('[laranail.toolkit.access-log]', $notices[1]);
    }

    /**
     * A directory inside the package, used as the scope's base path.
     *
     * Not the package root, which is the default: the root also holds this
     * suite's vendor/, so every framework closure binding and framework view
     * namespace would read as the toolkit's own and be reported as bare.
     */
    private function packagePath(string $directory): string
    {
        return dirname(__DIR__, 2) . '/' . $directory;
    }

    private function scope(?string $basePath = null): NamingScope
    {
        return NamingScope::for(
            package: 'laranail/toolkit',
            ownerNamespace: 'Simtabi\\Laranail\\Toolkit\\',
            basePath: $basePath,
            prefixes: [
                NameRegistry::ContainerAlias->value => [
                    'laranail-toolkit', 'laranail/toolkit', 'laranail.toolkit',
                    'laranail.archiver', 'laranail.livewire', 'laranail.llm',
                ],
            ],
        );
    }
}
