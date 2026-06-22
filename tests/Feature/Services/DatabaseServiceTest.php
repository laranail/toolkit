<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Psr\Log\NullLogger;
use Simtabi\Laranail\Toolkit\Services\DatabaseService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CounterWidget extends Model
{
    protected $table = 'counter_widgets';

    public $timestamps = false;

    protected $guarded = [];
}

class DatabaseServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('counter_widgets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('counter_widgets');

        parent::tearDown();
    }

    private function service(string $basePath): DatabaseService
    {
        return new DatabaseService(new NullLogger(), $this->app->make('session.store'), $basePath);
    }

    public function test_is_joined_detects_a_join(): void
    {
        $service = $this->service($this->app->basePath());

        $joined = CounterWidget::query()
            ->join('counter_widgets as c2', 'c2.id', '=', 'counter_widgets.id');

        $this->assertTrue($service->isJoined($joined, 'counter_widgets as c2'));
        $this->assertFalse($service->isJoined(CounterWidget::query(), 'counter_widgets as c2'));
    }

    public function test_modify_timestamps_sets_columns_without_touching_updates(): void
    {
        $widget = CounterWidget::create([]);
        $service = $this->service($this->app->basePath());

        $result = $service->modifyTimestamps(['published_at' => '2026-01-01 00:00:00'], $widget);

        $this->assertTrue($result);
        $this->assertSame('2026-01-01 00:00:00', (string) $widget->fresh()->published_at);
    }

    public function test_modify_timestamps_returns_false_for_empty_input(): void
    {
        $widget = CounterWidget::create([]);

        $this->assertFalse($this->service($this->app->basePath())->modifyTimestamps([], $widget));
    }

    public function test_handle_view_count_increments_once_per_session(): void
    {
        $widget = CounterWidget::create([]);
        $service = $this->service($this->app->basePath());

        $this->assertTrue($service->handleViewCount($widget, 'widget.views'));
        $this->assertSame(1, (int) $widget->fresh()->views);

        // Second call in the same session is a no-op.
        $this->assertFalse($service->handleViewCount($widget, 'widget.views'));
        $this->assertSame(1, (int) $widget->fresh()->views);
    }

    public function test_generate_relationship_sync_data_keys_by_id(): void
    {
        $service = $this->service($this->app->basePath());

        $data = $service->generateRelationshipSyncData(['a', 'b'], ['role' => 'member']);

        $this->assertArrayHasKey('a', $data);
        $this->assertArrayHasKey('b', $data);
        $this->assertSame('member', $data['a']['role']);
        $this->assertArrayHasKey('id', $data['a']);
    }
}
