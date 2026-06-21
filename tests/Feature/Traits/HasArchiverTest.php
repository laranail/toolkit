<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasArchiver;

class ArchivableWidget extends Model
{
    use HasArchiver;

    protected $table = 'archivable_widgets';

    public $timestamps = false;

    protected $fillable = ['name', 'archived_at'];
}

#[Group('traits')]
class HasArchiverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('archivable_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('archived_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('archivable_widgets');
        parent::tearDown();
    }

    public function test_archive_scope_hides_archived_rows_by_default(): void
    {
        ArchivableWidget::create(['name' => 'live']);
        ArchivableWidget::create(['name' => 'gone', 'archived_at' => now()]);

        $this->assertSame(1, ArchivableWidget::query()->count());
        $this->assertSame('live', ArchivableWidget::query()->first()?->name);
    }

    public function test_with_archived_includes_everything(): void
    {
        ArchivableWidget::create(['name' => 'live']);
        ArchivableWidget::create(['name' => 'gone', 'archived_at' => now()]);

        $this->assertSame(2, ArchivableWidget::query()->withArchived()->count());
        $this->assertSame(1, ArchivableWidget::query()->onlyArchived()->count());
    }

    public function test_archive_and_unarchive_toggle_the_column(): void
    {
        $widget = ArchivableWidget::create(['name' => 'live']);

        $this->assertFalse($widget->isArchived());

        $widget->archive();
        $this->assertTrue($widget->isArchived());
        $this->assertSame(0, ArchivableWidget::query()->count());

        $widget->unArchive();
        $this->assertFalse($widget->isArchived());
        $this->assertSame(1, ArchivableWidget::query()->count());
    }

    public function test_archiver_resolves_the_module_service(): void
    {
        $widget = new ArchivableWidget();

        $this->assertInstanceOf(ArchiverServiceInterface::class, $widget->archiver());
    }
}
