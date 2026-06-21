<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\CacheChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\ConsoleChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\DatabaseChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\FileChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\LogChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class LocalChannelsTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }

            foreach (glob($file . '.*') ?: [] as $rotated) {
                @unlink($rotated);
            }
        }

        $this->tempFiles = [];

        parent::tearDown();
    }

    public function test_log_channel_writes_to_the_log(): void
    {
        Log::shouldReceive('log')
            ->once()
            ->withArgs(fn ($level, $message) => $level === 'warning' && $message === 'logged body');

        $channel = new LogChannel();

        $this->assertTrue($channel->send(new NotificationMessage('logged body', level: 'warning')));
    }

    public function test_cache_channel_stores_an_entry(): void
    {
        $channel = new CacheChannel(['key_prefix' => 'notif_']);

        $message = new NotificationMessage('cached body', options: ['id' => 'abc']);

        $this->assertTrue($channel->send($message));

        $stored = Cache::get('notif_abc');

        $this->assertIsArray($stored);
        $this->assertSame('cached body', $stored['message']);
    }

    public function test_file_channel_appends_a_json_line(): void
    {
        $path = sys_get_temp_dir() . '/laranail-notif-' . uniqid() . '.log';
        $this->tempFiles[] = $path;

        $channel = new FileChannel(['path' => $path]);

        $this->assertTrue($channel->send(new NotificationMessage('file body')));

        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('file body', $contents);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim($contents), true);
        $this->assertSame('file body', $decoded['message']);
    }

    public function test_database_channel_inserts_a_row(): void
    {
        Schema::create('notifications', function ($table): void {
            $table->id();
            $table->text('message');
            $table->text('data')->nullable();
            $table->string('type')->default('general');
            $table->boolean('read')->default(false);
            $table->timestamps();
        });

        $channel = new DatabaseChannel(['table' => 'notifications']);

        $this->assertTrue($channel->send(new NotificationMessage('db body')));

        $this->assertDatabaseHas('notifications', ['message' => 'db body']);

        Schema::drop('notifications');
    }

    public function test_console_channel_writes_to_injected_output(): void
    {
        $output = new BufferedOutput();
        $channel = new ConsoleChannel([], $output);

        $this->assertTrue($channel->send(new NotificationMessage('console body', level: 'info')));

        $this->assertStringContainsString('console body', $output->fetch());
    }
}
