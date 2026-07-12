<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Simtabi\Laranail\Toolkit\Services\ValidationService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Stringable;

#[Group('services')]
class ValidationServiceTest extends TestCase
{
    private function service(): ValidationService
    {
        return new ValidationService($this->app->make('session.store'), new NullLogger());
    }

    public function test_error_bag_message_is_empty_html_without_errors(): void
    {
        $html = $this->service()->getErrorBagMessage('email');

        $this->assertInstanceOf(HtmlString::class, $html);
        $this->assertSame('', (string) $html);
    }

    public function test_error_bag_message_renders_the_first_error(): void
    {
        Session::put('errors', new MessageBag(['email' => ['The email is required.']]));

        $html = (string) $this->service()->getErrorBagMessage('email');

        $this->assertStringContainsString('The email is required.', $html);
        $this->assertStringContainsString('has-error', $html);
        $this->assertStringContainsString('error-msg', $html);
    }

    public function test_error_bag_message_class_reflects_state(): void
    {
        $service = $this->service();
        $this->assertSame('success', $service->getErrorBagMessageClass('email'));

        Session::put('errors', new MessageBag(['email' => ['bad']]));
        $this->assertSame('error', $this->service()->getErrorBagMessageClass('email'));
    }

    public function test_has_error_css_class_reflects_state(): void
    {
        Session::put('errors', new MessageBag(['name' => ['bad']]));
        $service = $this->service();

        $this->assertSame('has-error', $service->getHasErrorCssClass('name'));
        $this->assertSame('has-success', $service->getHasErrorCssClass('email'));
    }

    public function test_checkbox_status(): void
    {
        $service = $this->service();
        $this->assertSame('checked', $service->getCheckboxStatus('1', 'opt'));
        $this->assertNull($service->getCheckboxStatus('', 'opt'));
    }

    public function test_old_input_resolves_session_then_model_then_default(): void
    {
        $service = $this->service();

        Session::put('_old_input.name', 'from-session');
        $this->assertSame('from-session', $service->oldInput('name'));

        Session::forget('_old_input.name');
        $model = (object) ['name' => 'from-model'];
        $this->assertSame('from-model', $service->oldInput('name', $model));
        $this->assertSame('fallback', $service->oldInput('missing', $model, 'fallback'));
    }

    public function test_old_input_can_coerce_to_bool(): void
    {
        Session::put('_old_input.flag', '1');

        $this->assertTrue($this->service()->oldInput('flag', null, null, true));
    }

    public function test_fetch_model_data(): void
    {
        $service = $this->service();
        $model = (object) ['title' => 'Hello'];

        $this->assertSame('Hello', $service->fetchModelData('title', $model));
        $this->assertSame('def', $service->fetchModelData('missing', $model, 'def'));
        $this->assertSame('', $service->fetchModelData('missing'));
    }

    public function test_is_valid_database_connection(): void
    {
        Schema::create('settings_probe', fn ($t) => $t->increments('id'));

        $this->assertTrue($this->service()->isValidDatabaseConnection('settings_probe'));
        $this->assertFalse($this->service()->isValidDatabaseConnection('does_not_exist'));

        Schema::drop('settings_probe');
    }

    public function test_is_valid_database_connection_logs_and_returns_false_on_failure(): void
    {
        $logger = new class() extends AbstractLogger
        {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $service = new ValidationService($this->app->make('session.store'), $logger);

        // Point the default connection at an unsupported driver so the schema
        // lookup throws — exercising the catch/log/return-false guard. Restored
        // before teardown so the in-memory database can still be cleaned up.
        $original = config('database.default');
        config()->set('database.connections.broken', ['driver' => 'no-such-driver', 'database' => ':memory:']);
        config()->set('database.default', 'broken');

        try {
            $this->assertFalse($service->isValidDatabaseConnection('settings'));
        } finally {
            config()->set('database.default', $original);
            DB::purge('broken');
        }

        $this->assertCount(1, $logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
        $this->assertSame('Database connection validation failed', $logger->records[0]['message']);
        $this->assertSame('settings', $logger->records[0]['context']['table']);
        $this->assertIsString($logger->records[0]['context']['error']);
        $this->assertNotSame('', $logger->records[0]['context']['error']);
    }
}
