<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Psr\Log\NullLogger;
use Simtabi\Laranail\Toolkit\Services\DatabaseService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Fixture model backed by a temp sqlite table for the DatabaseService tests.
 */
class DbWidget extends Model
{
    protected $table = 'db_widgets';

    public $timestamps = true;

    protected $guarded = [];
}

class DatabaseServiceCoverageTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('db_widgets', function ($table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('views')->default(0);
            $table->timestamps();
        });

        $this->base = sys_get_temp_dir() . '/laranail-db-' . bin2hex(random_bytes(6));
        mkdir($this->base, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->base);
        parent::tearDown();
    }

    private function service(): DatabaseService
    {
        return new DatabaseService(new NullLogger(), $this->app['session.store'], $this->base);
    }

    public function test_is_joined_detects_a_joined_table_via_eloquent_builder(): void
    {
        $query = DbWidget::query()->join('users', 'users.id', '=', 'db_widgets.id');

        $this->assertTrue($this->service()->isJoined($query, 'users'));
        $this->assertFalse($this->service()->isJoined($query, 'roles'));
    }

    public function test_is_joined_is_false_for_query_without_joins(): void
    {
        $this->assertFalse($this->service()->isJoined(DbWidget::query(), 'users'));
    }

    public function test_is_joined_is_false_for_non_builder_input(): void
    {
        $this->assertFalse($this->service()->isJoined('not a builder', 'users'));
    }

    public function test_modify_timestamps_writes_dates_and_disables_auto_timestamps(): void
    {
        $widget = DbWidget::query()->create(['name' => 'a']);
        $custom = '2001-01-01 00:00:00';

        $result = $this->service()->modifyTimestamps(['created_at' => $custom], $widget);

        $this->assertTrue($result);
        $this->assertSame($custom, (string) DbWidget::query()->find($widget->id)?->created_at);
    }

    public function test_modify_timestamps_is_false_for_empty_dates(): void
    {
        $widget = DbWidget::query()->create(['name' => 'b']);

        $this->assertFalse($this->service()->modifyTimestamps([], $widget));
    }

    public function test_handle_view_count_increments_once_per_session_key(): void
    {
        $widget = DbWidget::query()->create(['name' => 'c', 'views' => 0]);

        $this->assertTrue($this->service()->handleViewCount($widget, 'views.session'));
        $this->assertSame(1, (int) DbWidget::query()->find($widget->id)?->views);

        // Second call with the same session key is a no-op.
        $this->assertFalse($this->service()->handleViewCount($widget, 'views.session'));
        $this->assertSame(1, (int) DbWidget::query()->find($widget->id)?->views);
    }

    public function test_set_morph_class_names_merges_into_app_aliases(): void
    {
        config()->set('app.aliases', ['existing' => 'X']);

        $this->service()->setMorphClassNames(['widget' => DbWidget::class]);

        $aliases = config('app.aliases');
        $this->assertSame('X', $aliases['existing']);
        $this->assertSame(DbWidget::class, $aliases['widget']);
    }

    public function test_generate_relationship_sync_data_keys_by_id_and_attaches_data(): void
    {
        $out = $this->service()->generateRelationshipSyncData(['10', '20'], ['role' => 'admin']);

        $this->assertArrayHasKey('10', $out);
        $this->assertArrayHasKey('20', $out);
        $this->assertContains('admin', $out['10']);
    }

    public function test_generate_relationship_sync_data_accepts_scalar_and_skips_blanks(): void
    {
        $out = $this->service()->generateRelationshipSyncData(['', '  ', 'abc']);

        // Blank/whitespace-only ids are dropped; only the real id survives.
        $this->assertSame(['abc'], array_keys($out));
    }

    public function test_clear_cache_deletes_only_confined_facade_files(): void
    {
        $cacheDir = $this->base . '/storage/framework/cache';
        mkdir($cacheDir, 0755, true);
        file_put_contents($cacheDir . '/facade-abc.php', '<?php');
        file_put_contents($cacheDir . '/keep.txt', 'keep');

        $this->assertTrue($this->service()->clearCache());

        $this->assertFileDoesNotExist($cacheDir . '/facade-abc.php');
        $this->assertFileExists($cacheDir . '/keep.txt');
    }

    public function test_clear_log_files_deletes_logs_but_keeps_gitignore(): void
    {
        $logsDir = $this->base . '/storage/logs';
        mkdir($logsDir, 0755, true);
        file_put_contents($logsDir . '/laravel.log', 'noise');
        file_put_contents($logsDir . '/.gitignore', '*');

        $this->assertTrue($this->service()->clearLogFiles());

        $this->assertFileDoesNotExist($logsDir . '/laravel.log');
        $this->assertFileExists($logsDir . '/.gitignore');
    }

    public function test_delete_storage_symlink_removes_existing_public_storage(): void
    {
        $publicDir = $this->base . '/public';
        mkdir($publicDir, 0755, true);
        file_put_contents($publicDir . '/storage', 'link-placeholder');

        $this->assertTrue($this->service()->deleteStorageSymlink());
        $this->assertFileDoesNotExist($publicDir . '/storage');
    }

    public function test_delete_storage_symlink_is_false_when_absent(): void
    {
        $this->assertFalse($this->service()->deleteStorageSymlink());
    }

    public function test_modify_timestamps_returns_false_when_save_throws(): void
    {
        $widget = DbWidget::query()->create(['name' => 'd']);

        // Writing to a non-existent column makes save() throw; the service
        // must swallow it and return false.
        $this->assertFalse($this->service()->modifyTimestamps(['no_such_column' => 'x'], $widget));
    }

    public function test_handle_view_count_returns_false_when_increment_throws(): void
    {
        $widget = DbWidget::query()->create(['name' => 'e']);

        // Drop the table out from under the increment so the query throws.
        Schema::drop('db_widgets');

        $this->assertFalse($this->service()->handleViewCount($widget, 'broken.session'));
    }

    public function test_clear_cache_returns_true_when_directories_absent(): void
    {
        // No storage/bootstrap dirs under the temp base: the confined-dir
        // lookups return null and the method still succeeds.
        $this->assertTrue($this->service()->clearCache());
    }

    public function test_clear_log_files_returns_true_when_directories_absent(): void
    {
        $this->assertTrue($this->service()->clearLogFiles());
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
