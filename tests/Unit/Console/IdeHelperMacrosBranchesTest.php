<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Console;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Simtabi\Laranail\Toolkit\Commands\IdeHelperMacros;
use Simtabi\Laranail\Toolkit\Macros\FactoryBuilderMixin;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use stdClass;

/**
 * Targets the defensive/rarely-hit branches of the ide-helper-macros generator:
 * the write-failure exit, the default output path, the empty-macro and absent
 * factory-mixin skips, the non-Closure macro reflection forms, and the
 * unresolvable default-value rendering.
 */
class IdeHelperMacrosBranchesTest extends TestCase
{
    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = sys_get_temp_dir() . '/laranail_ide_helper_branch_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->output)) {
            @unlink($this->output);
        }

        parent::tearDown();
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invoke(object $object, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod($object, $method))->invokeArgs($object, $args);
    }

    // -----------------------------------------------------------------------
    // handle(): write failure + default path
    // -----------------------------------------------------------------------

    public function test_handle_fails_when_the_stub_cannot_be_written(): void
    {
        File::shouldReceive('ensureDirectoryExists')->andReturnNull();
        File::shouldReceive('put')->andReturnFalse();

        $this->artisan('laranail::toolkit.ide-helper-macros', ['--path' => $this->output])
            ->expectsOutputToContain('Unable to write the IDE-helper stub')
            ->assertExitCode(1);
    }

    public function test_handle_resolves_the_default_path_when_no_option_is_given(): void
    {
        // Fake the write so the real default location under base_path is never
        // touched; the default-path resolution branch still runs.
        File::shouldReceive('ensureDirectoryExists')->andReturnNull();
        File::shouldReceive('put')->andReturnTrue();

        $this->artisan('laranail::toolkit.ide-helper-macros')
            ->expectsOutputToContain('Regenerated IDE-helper macro stub')
            ->assertExitCode(0);
    }

    // -----------------------------------------------------------------------
    // renderClassBlock(): empty macros + readStaticMacros() missing property
    // -----------------------------------------------------------------------

    public function test_render_class_block_returns_null_for_a_target_with_no_macros(): void
    {
        $command = new IdeHelperMacros();

        // stdClass has no static $macros property, so readStaticMacros() returns
        // an empty map and the class block is skipped entirely.
        $result = $this->invoke($command, 'renderClassBlock', ['Dummy', stdClass::class, false]);

        $this->assertNull($result);
    }

    // -----------------------------------------------------------------------
    // renderFactoryMixinClassBlock(): mixin absent
    // -----------------------------------------------------------------------

    public function test_factory_mixin_block_is_skipped_when_the_mixin_is_absent(): void
    {
        $command = new IdeHelperMacros();

        Factory::flushMacros();

        try {
            $result = $this->invoke($command, 'renderFactoryMixinClassBlock');

            $this->assertNull($result);
        } finally {
            // Restore the mixin the toolkit registers at boot.
            Factory::mixin(new FactoryBuilderMixin());
        }
    }

    // -----------------------------------------------------------------------
    // reflectMacro(): array / invokable / unresolvable / unsupported forms
    // -----------------------------------------------------------------------

    public function test_reflect_macro_handles_an_array_callable(): void
    {
        $command = new IdeHelperMacros();

        // A [class, method] pair (kept as an array, not a first-class callable)
        // so the reflector's is_array() branch is exercised. The method name is
        // held in a variable so it is not rewritten to a first-class callable.
        $method = 'lower';
        $arrayCallable = [Str::class, $method];
        $result = $this->invoke($command, 'reflectMacro', [$arrayCallable]);

        $this->assertInstanceOf(ReflectionMethod::class, $result);
    }

    public function test_reflect_macro_handles_an_invokable_object(): void
    {
        $command = new IdeHelperMacros();

        $invokable = new class()
        {
            public function __invoke(): void {}
        };

        $result = $this->invoke($command, 'reflectMacro', [$invokable]);

        $this->assertInstanceOf(ReflectionMethod::class, $result);
    }

    public function test_reflect_macro_returns_null_for_an_unresolvable_array_callable(): void
    {
        $command = new IdeHelperMacros();

        // __call makes the pair pass the callable type-hint, yet the named method
        // does not really exist, so ReflectionMethod throws and the reflector
        // swallows it into a null.
        $magic = new class()
        {
            /**
             * @param array<int, mixed> $arguments
             */
            public function __call(string $name, array $arguments): void {}
        };

        $result = $this->invoke($command, 'reflectMacro', [[$magic, 'phantomMethod']]);

        $this->assertNull($result);
    }

    public function test_reflect_macro_returns_null_for_an_unsupported_callable_form(): void
    {
        $command = new IdeHelperMacros();

        // A bare string function name is callable but is neither a Closure, an
        // array pair, nor an object, so it falls through to the null return.
        $result = $this->invoke($command, 'reflectMacro', ['strlen']);

        $this->assertNull($result);
    }

    public function test_reflect_macro_handles_a_closure(): void
    {
        $command = new IdeHelperMacros();

        $result = $this->invoke($command, 'reflectMacro', [static fn (): int => 1]);

        $this->assertInstanceOf(ReflectionFunction::class, $result);
    }

    // -----------------------------------------------------------------------
    // renderDefault(): unresolvable default value
    // -----------------------------------------------------------------------

    public function test_render_default_returns_null_for_an_unresolvable_default(): void
    {
        $command = new IdeHelperMacros();

        // A required internal-function parameter has no retrievable default, so
        // getDefaultValue() throws and the renderer falls back to 'null'.
        $parameter = new ReflectionParameter('strlen', 'string');

        $this->assertSame('null', $this->invoke($command, 'renderDefault', [$parameter]));
    }
}
