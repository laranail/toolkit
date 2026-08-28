<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use BadMethodCallException;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Macros\MacroableModels;

final class MacroablePost extends Model
{
    public $timestamps = false;

    protected $table = 'macroable_posts';

    protected $guarded = [];

    protected string $secret = 'from the model';
}

final class MacroableComment extends Model
{
    public $timestamps = false;

    protected $table = 'macroable_comments';

    protected $guarded = [];
}

final class MacroableModelsTest extends TestCase
{
    private MacroableModels $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new MacroableModels;
    }

    protected function tearDown(): void
    {
        $this->registry->flush();

        parent::tearDown();
    }

    public function test_a_macro_is_callable_on_the_model_it_was_registered_for(): void
    {
        $this->registry->addMacro(MacroablePost::class, 'shout', fn (): string => 'post!');

        self::assertSame('post!', MacroablePost::query()->shout());
    }

    public function test_the_same_macro_is_undefined_on_another_model(): void
    {
        // This is the whole point. Builder::macro() is global; this is not.
        $this->registry->addMacro(MacroablePost::class, 'shout', fn (): string => 'post!');

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage(MacroableComment::class . '::shout()');

        MacroableComment::query()->shout();
    }

    public function test_one_name_may_serve_several_models_differently(): void
    {
        $this->registry->addMacro(MacroablePost::class, 'label', fn (): string => 'a post');
        $this->registry->addMacro(MacroableComment::class, 'label', fn (): string => 'a comment');

        self::assertSame('a post', MacroablePost::query()->label());
        self::assertSame('a comment', MacroableComment::query()->label());
    }

    public function test_arguments_are_passed_through(): void
    {
        $this->registry->addMacro(
            MacroablePost::class,
            'echoes',
            fn (string $a, int $b): string => "{$a}-{$b}",
        );

        self::assertSame('x-2', MacroablePost::query()->echoes('x', 2));
    }

    public function test_this_inside_a_macro_is_the_model_with_its_scope(): void
    {
        // Bound to the model instance, which also gives the closure that
        // model's class scope — so protected members are reachable. That is
        // what makes accessor-style macros work.
        $this->registry->addMacro(MacroablePost::class, 'reveal', function (): string {
            /** @var MacroablePost $this */
            return $this->secret;
        });

        self::assertSame('from the model', MacroablePost::query()->reveal());
    }

    public function test_it_reports_which_models_implement_a_name(): void
    {
        $this->registry->addMacro(MacroablePost::class, 'shared', fn (): int => 1);
        $this->registry->addMacro(MacroableComment::class, 'shared', fn (): int => 2);

        self::assertSame(
            [MacroablePost::class, MacroableComment::class],
            $this->registry->modelsThatImplement('shared'),
        );
        self::assertSame([], $this->registry->modelsThatImplement('never-registered'));
    }

    public function test_model_has_macro(): void
    {
        $this->registry->addMacro(MacroablePost::class, 'only', fn (): int => 1);

        self::assertTrue($this->registry->modelHasMacro(MacroablePost::class, 'only'));
        self::assertFalse($this->registry->modelHasMacro(MacroableComment::class, 'only'));
        self::assertFalse($this->registry->modelHasMacro(MacroablePost::class, 'other'));
    }

    public function test_removing_a_macro_makes_it_undefined_again(): void
    {
        $this->registry->addMacro(MacroablePost::class, 'temporary', fn (): int => 1);
        self::assertTrue($this->registry->removeMacro(MacroablePost::class, 'temporary'));

        $this->expectException(BadMethodCallException::class);

        MacroablePost::query()->temporary();
    }

    public function test_removing_something_unregistered_reports_false(): void
    {
        self::assertFalse($this->registry->removeMacro(MacroablePost::class, 'nothing'));
    }

    public function test_removing_one_model_leaves_the_other_working(): void
    {
        $this->registry->addMacro(MacroablePost::class, 'both', fn (): string => 'post');
        $this->registry->addMacro(MacroableComment::class, 'both', fn (): string => 'comment');

        $this->registry->removeMacro(MacroablePost::class, 'both');

        self::assertSame('comment', MacroableComment::query()->both());
    }

    public function test_it_lists_a_models_macros_with_parameter_names(): void
    {
        $this->registry->addMacro(MacroablePost::class, 'withArgs', fn (string $alpha, int $beta): string => '');

        $macros = $this->registry->macrosForModel(MacroablePost::class);

        self::assertArrayHasKey('withArgs', $macros);
        self::assertSame('withArgs', $macros['withArgs']['name']);
        // Names, not ReflectionParameter objects — the code this came from
        // leaked reflection into a public API.
        self::assertSame(['alpha', 'beta'], $macros['withArgs']['parameters']);
    }

    public function test_a_non_model_class_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a subclass of');

        $this->registry->addMacro(self::class, 'nope', fn (): int => 1);
    }

    public function test_a_class_that_does_not_exist_is_refused_clearly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        // @phpstan-ignore-next-line — deliberately invalid input
        $this->registry->addMacro('App\\Models\\Ghost', 'nope', fn (): int => 1);
    }

    public function test_get_macro_returns_the_closures_by_model(): void
    {
        $this->registry->addMacro(MacroablePost::class, 'named', fn (): int => 1);

        self::assertArrayHasKey(MacroablePost::class, $this->registry->getMacro('named'));
        self::assertSame([], $this->registry->getMacro('missing'));
    }

    public function test_the_container_hands_out_one_shared_registry(): void
    {
        // A fresh instance per resolve would hand back an empty registry while
        // the Builder macros it had already registered stayed behind.
        self::assertSame(
            $this->app->make(MacroableModels::class),
            $this->app->make(MacroableModels::class),
        );
    }
}
