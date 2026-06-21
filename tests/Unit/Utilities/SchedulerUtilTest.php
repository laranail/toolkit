<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Utilities;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Utilities\SchedulerUtil;

class SchedulerUtilTest extends TestCase
{
    protected SchedulerUtil $schedulerUtil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedulerUtil = new SchedulerUtil();
    }

    public function test_can_get_schedule_summary()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);

        $this->app->instance(Schedule::class, $schedule);

        $summary = $this->schedulerUtil->getScheduleSummary();

        $this->assertIsArray($summary);
        $this->assertEmpty($summary);
    }

    public function test_summary_and_overdue_handle_real_scheduled_events()
    {
        // A real scheduled event exercises isDue() -> nextRunDate() (regression
        // for the getNextRunDate() bug, which does not exist on Laravel 13).
        $schedule = $this->app->make(Schedule::class);
        $schedule->command('inspire')->everyMinute();

        $summary = $this->schedulerUtil->getScheduleSummary();

        $this->assertCount(1, $summary);
        $this->assertArrayHasKey('is_due', $summary[0]);
        $this->assertArrayHasKey('next_run', $summary[0]);
        $this->assertIsBool($this->schedulerUtil->hasOverdueTasks());
    }

    public function test_can_check_for_overdue_tasks()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);

        $this->app->instance(Schedule::class, $schedule);

        $hasOverdue = $this->schedulerUtil->hasOverdueTasks();

        $this->assertIsBool($hasOverdue);
    }

    public function test_returns_false_when_no_overdue_tasks()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);

        $this->app->instance(Schedule::class, $schedule);

        $hasOverdue = $this->schedulerUtil->hasOverdueTasks();

        $this->assertFalse($hasOverdue);
    }

    public function test_ignores_running_tasks_for_overdue_check()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);

        $this->app->instance(Schedule::class, $schedule);

        $hasOverdue = $this->schedulerUtil->hasOverdueTasks();

        $this->assertFalse($hasOverdue);
    }

    public function test_handles_empty_schedule()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);

        $this->app->instance(Schedule::class, $schedule);

        $summary = $this->schedulerUtil->getScheduleSummary();
        $hasOverdue = $this->schedulerUtil->hasOverdueTasks();

        $this->assertIsArray($summary);
        $this->assertEmpty($summary);
        $this->assertFalse($hasOverdue);
    }

    public function test_logs_scheduled_events()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);

        $this->app->instance(Schedule::class, $schedule);

        // Mock Log facade to verify logging
        Log::shouldReceive('info')
            ->with(\Mockery::type('string'))
            ->once();

        $this->schedulerUtil->getScheduleSummary();
    }

    public function test_handles_multiple_events()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);

        $this->app->instance(Schedule::class, $schedule);

        $summary = $this->schedulerUtil->getScheduleSummary();

        $this->assertIsArray($summary);
        $this->assertEmpty($summary);
    }
}
