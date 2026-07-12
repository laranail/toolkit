<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Console;

use Carbon\FactoryImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Exercises the ide-helper-macros generator: it must register under the org
 * namespace + alias, generate a parseable stub, and produce `@method` tags that
 * exactly mirror the live registered macros for every macroable target.
 */
class IdeHelperMacrosCommandTest extends TestCase
{
    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = sys_get_temp_dir() . '/laranail_ide_helper_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->output)) {
            @unlink($this->output);
        }

        parent::tearDown();
    }

    public function test_command_registers_under_the_org_namespaced_name_and_alias(): void
    {
        $commands = $this->app[Kernel::class]->all();

        $this->assertArrayHasKey('laranail::toolkit.ide-helper-macros', $commands);
        $this->assertArrayHasKey('ide-helper:macros', $commands);
        $this->assertSame(
            $commands['laranail::toolkit.ide-helper-macros'],
            $commands['ide-helper:macros'],
        );
    }

    public function test_command_generates_a_stub_at_the_given_path(): void
    {
        $this->artisan('laranail::toolkit.ide-helper-macros', ['--path' => $this->output])
            ->assertExitCode(0);

        $this->assertFileExists($this->output);

        $contents = (string) file_get_contents($this->output);
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertStringContainsString('phpcs:ignoreFile', $contents);
    }

    public function test_generated_methods_match_live_registered_macros(): void
    {
        $this->artisan('laranail::toolkit.ide-helper-macros', ['--path' => $this->output])
            ->assertExitCode(0);

        $contents = (string) file_get_contents($this->output);

        foreach ($this->targets() as $target) {
            $stubbed = $this->stubMethodsFor($contents, $target['class']);
            $registered = $target['macros']();

            sort($stubbed);
            sort($registered);

            $this->assertSame(
                $registered,
                $stubbed,
                "Generated stub for {$target['class']} does not match the registered macros.",
            );
        }
    }

    public function test_generated_stub_documents_the_factory_mixin(): void
    {
        $this->artisan('laranail::toolkit.ide-helper-macros', ['--path' => $this->output])
            ->assertExitCode(0);

        $contents = (string) file_get_contents($this->output);

        $this->assertSame(
            ['withoutEvents'],
            $this->stubMethodsFor($contents, Factory::class),
        );
    }

    public function test_generated_stub_passes_the_committed_drift_contract(): void
    {
        // Regenerating then re-parsing must satisfy the same both-directions
        // contract the committed-stub drift test enforces.
        $this->artisan('laranail::toolkit.ide-helper-macros', ['--path' => $this->output])
            ->assertExitCode(0);

        $contents = (string) file_get_contents($this->output);

        $str = $this->stubMethodsFor($contents, Str::class);
        foreach ($str as $macro) {
            $this->assertTrue(Str::hasMacro($macro));
        }
        $this->assertContains('kebabToTitle', $str);
    }

    /**
     * @return array<string, array{class: class-string, macros: callable(): list<string>}>
     */
    private function targets(): array
    {
        return [
            'Str' => ['class' => Str::class, 'macros' => $this->macroReader(Str::class, 'macros')],
            'Stringable' => ['class' => Stringable::class, 'macros' => $this->macroReader(Stringable::class, 'macros')],
            'Collection' => ['class' => Collection::class, 'macros' => $this->macroReader(Collection::class, 'macros')],
            'Arr' => ['class' => Arr::class, 'macros' => $this->macroReader(Arr::class, 'macros')],
            'QueryBuilder' => ['class' => QueryBuilder::class, 'macros' => $this->macroReader(QueryBuilder::class, 'macros')],
            'EloquentBuilder' => ['class' => EloquentBuilder::class, 'macros' => $this->macroReader(EloquentBuilder::class, 'macros')],
            'Request' => ['class' => Request::class, 'macros' => $this->macroReader(Request::class, 'macros')],
            'Carbon' => ['class' => Carbon::class, 'macros' => fn (): array => array_keys(
                FactoryImmutable::getDefaultInstance()->getSettings()['macros'] ?? [],
            )],
        ];
    }

    /**
     * @param class-string $class
     *
     * @return callable(): list<string>
     */
    private function macroReader(string $class, string $property): callable
    {
        return static function () use ($class, $property): array {
            $reflection = new \ReflectionClass($class);

            if (!$reflection->hasProperty($property)) {
                return [];
            }

            return array_values(array_keys((array) $reflection->getProperty($property)->getValue()));
        };
    }

    /**
     * Extract the `@method` names declared on a given stub class (textual parse,
     * matching the committed-stub drift test).
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    private function stubMethodsFor(string $contents, string $class): array
    {
        $namespace = trim(substr($class, 0, (int) strrpos($class, '\\')), '\\');
        $shortName = substr($class, (int) strrpos($class, '\\') + 1);

        $nsPattern = '/namespace\s+' . preg_quote($namespace, '/') . '\s*\{(.*?)\n\}/s';

        if (preg_match($nsPattern, $contents, $nsMatch) !== 1) {
            return [];
        }

        $classPattern = '#/\*\*((?:(?!\*/).)*)\*/\s*class\s+' . preg_quote($shortName, '#') . '\s*\{\}#s';

        if (preg_match($classPattern, $nsMatch[1], $classMatch) !== 1) {
            return [];
        }

        preg_match_all(
            '/@method\s+(?:static\s+)?[^\s]+(?:\|[^\s]+)*\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/',
            $classMatch[1],
            $methodMatches,
        );

        return array_values(array_unique($methodMatches[1]));
    }
}
