<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Morph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Morph\Exceptions\UnknownMorphAliasException;
use Simtabi\Laranail\Toolkit\Morph\MorphAliasRegistry;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/** A scalar-keyed Eloquent model used as a mapped morph subject. */
class MorphWidget extends Model
{
    public $timestamps = false;

    protected $table = 'morph_widgets';

    protected $guarded = [];
}

/** A model whose key is deliberately non-scalar, to exercise the scalar-key guard. */
class NonScalarKeyModel extends Model
{
    public $timestamps = false;

    protected $table = 'non_scalar_key_models';

    protected $guarded = [];

    public function getKey(): mixed
    {
        return ['composite', 'key'];
    }
}

#[Group('morph')]
class MorphAliasRegistryTest extends TestCase
{
    public function test_map_and_bidirectional_lookups(): void
    {
        $registry = $this->registry();

        $this->assertSame($this->map(), $registry->map());
        $this->assertSame('widget', $registry->aliasFor(MorphWidget::class));
        $this->assertSame(MorphWidget::class, $registry->classFor('widget'));
        $this->assertTrue($registry->hasAlias('widget'));
        $this->assertFalse($registry->hasAlias('ghost'));
        $this->assertTrue($registry->hasClass(MorphWidget::class));
        $this->assertFalse($registry->hasClass(NonScalarKeyModel::class));
        $this->assertFalse($registry->isEmpty());
        $this->assertTrue((new MorphAliasRegistry([]))->isEmpty());
    }

    public function test_assert_known_alias_passes_for_mapped_and_throws_for_unknown(): void
    {
        $registry = $this->registry();

        $registry->assertKnownAlias('widget');

        $this->expectException(UnknownMorphAliasException::class);
        $this->expectExceptionMessage('Morph alias "ghost" is not in the governed morph map.');
        $registry->assertKnownAlias('ghost');
    }

    public function test_alias_for_throws_for_unmapped_class(): void
    {
        $this->expectException(UnknownMorphAliasException::class);
        $this->expectExceptionMessage(NonScalarKeyModel::class);
        $this->registry()->aliasFor(NonScalarKeyModel::class);
    }

    public function test_class_for_throws_for_unknown_alias(): void
    {
        $this->expectException(UnknownMorphAliasException::class);
        $this->registry()->classFor('ghost');
    }

    public function test_key_for_returns_scalar_key_as_string(): void
    {
        $model = new MorphWidget(['id' => 42]);
        $model->setAttribute('id', 42);

        $this->assertSame('42', $this->registry()->keyFor($model));
    }

    public function test_key_for_throws_on_non_scalar_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('scalar primary key');
        $this->registry()->keyFor(new NonScalarKeyModel);
    }

    public function test_alias_and_key_for_returns_alias_and_key_pair(): void
    {
        $model = new MorphWidget;
        $model->setAttribute('id', 7);

        $this->assertSame(['widget', '7'], $this->registry()->aliasAndKeyFor($model));
    }

    public function test_alias_and_key_for_guards_scalar_key_before_alias_lookup(): void
    {
        // A non-scalar key fails the scalar guard even when the class is also unmapped.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('scalar primary key');
        $this->registry()->aliasAndKeyFor(new NonScalarKeyModel);
    }

    public function test_resolve_finds_the_row_and_returns_null_when_gone(): void
    {
        Schema::create('morph_widgets', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        $widget = MorphWidget::query()->create(['name' => 'first']);

        $registry = $this->registry();
        $resolved = $registry->resolve('widget', (string) $widget->getKey());

        $this->assertInstanceOf(MorphWidget::class, $resolved);
        $this->assertSame($widget->getKey(), $resolved->getKey());
        $this->assertNull($registry->resolve('widget', '999999'));

        Schema::drop('morph_widgets');
    }

    public function test_resolve_throws_for_unknown_alias(): void
    {
        $this->expectException(UnknownMorphAliasException::class);
        $this->registry()->resolve('ghost', '1');
    }

    /** @return array<string, class-string<Model>> */
    private function map(): array
    {
        return ['widget' => MorphWidget::class];
    }

    private function registry(): MorphAliasRegistry
    {
        return new MorphAliasRegistry($this->map());
    }
}
