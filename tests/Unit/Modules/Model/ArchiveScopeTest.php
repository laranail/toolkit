<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Model\Scopes\ArchiveScope;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasArchiver;

/**
 * Bare model carrying the archive column but WITHOUT registering ArchiveScope as
 * a global scope, so the scope object can be driven directly in isolation.
 */
class ScopelessArchivable extends Model
{
    /** @use HasArchiver<self> */
    use HasArchiver;

    protected $table = 'scopeless_archivables';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['name', 'archived_at'];

    /**
     * Drop the global scope booted by HasArchiver so each test applies it by hand.
     */
    public static function bootHasArchiver(): void
    {
        // intentionally no addGlobalScope(): the unit under test is ArchiveScope.
    }
}

#[Group('model')]
class ArchiveScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('scopeless_archivables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('archived_at')->nullable();
        });

        ScopelessArchivable::query()->insert([
            ['name' => 'live', 'archived_at' => null],
            ['name' => 'gone', 'archived_at' => now()->toDateTimeString()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('scopeless_archivables');
        parent::tearDown();
    }

    public function test_apply_adds_a_where_null_constraint_on_the_archived_at_column(): void
    {
        $builder = ScopelessArchivable::query();

        new ArchiveScope()->apply($builder, $builder->getModel());

        $sql = $builder->toSql();

        $this->assertStringContainsString('is null', strtolower($sql));
        $this->assertStringContainsString('archived_at', $sql);

        // The constraint must actually hide the archived row.
        $this->assertSame(1, $builder->count());
        $this->assertSame('live', $builder->first()?->name);
    }

    public function test_extend_registers_the_archive_builder_macros(): void
    {
        $builder = ScopelessArchivable::query();

        new ArchiveScope()->extend($builder);

        foreach (['archive', 'unArchive', 'withArchived', 'withoutArchived', 'onlyArchived'] as $macro) {
            $this->assertTrue($builder->hasMacro($macro), "missing macro: {$macro}");
        }
    }

    public function test_with_archived_macro_returns_every_row(): void
    {
        $builder = ScopelessArchivable::query();
        new ArchiveScope()->extend($builder);

        $this->assertSame(2, $builder->withArchived()->count());
    }

    public function test_only_archived_macro_returns_archived_rows(): void
    {
        $builder = ScopelessArchivable::query();
        new ArchiveScope()->extend($builder);

        $rows = $builder->onlyArchived()->get();

        $this->assertSame(1, $rows->count());
        $this->assertSame('gone', $rows->first()?->name);
    }

    public function test_without_archived_macro_excludes_archived_rows(): void
    {
        // withoutArchived strips the global scope then re-adds the whereNull, so it
        // must work even without ArchiveScope applied as a global scope.
        $builder = ScopelessArchivable::query();
        new ArchiveScope()->extend($builder);

        $rows = $builder->withoutArchived()->get();

        $this->assertSame(1, $rows->count());
        $this->assertSame('live', $rows->first()?->name);
    }
}
