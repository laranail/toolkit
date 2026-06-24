<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Utilities;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class SchedulerUtil
{
    /**
     * Get a summary of the scheduled tasks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getScheduleSummary(): array
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        Log::info('Summarized ' . count($events) . ' scheduled event(s).');

        return collect($events)->map(fn (Event $event): array => [
            'command' => $event->command,
            'expression' => $event->expression,
            'description' => $event->description,
            'next_run' => $event->nextRunDate(),
            'is_due' => $this->isDue($event),
            'output' => $event->output,
        ])->values()->all();
    }

    /**
     * Whether any scheduled task is due to run now.
     */
    public function hasOverdueTasks(): bool
    {
        $schedule = app(Schedule::class);

        return collect($schedule->events())->contains(fn (Event $event): bool => $this->isDue($event));
    }

    /**
     * Evaluate the event's cron expression against the current time.
     */
    private function isDue(Event $event): bool
    {
        return new CronExpression($event->expression)->isDue(Carbon::now());
    }
}
