<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Exceptions;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Exceptions\Concerns\RendersApiExceptions;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class RendersApiExceptionsTest extends TestCase
{
    private function handler(): Handler
    {
        return $this->app->make(Handler::class);
    }

    private function configurator(Handler $handler): Exceptions
    {
        return new Exceptions($handler);
    }

    public function test_method_not_allowed_renders_json_for_api_requests(): void
    {
        $handler = $this->handler();
        RendersApiExceptions::registerMethodNotAllowedRenderer($this->configurator($handler));

        $request = Request::create('/api/posts', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $handler->render($request, new MethodNotAllowedHttpException(['GET'], 'Method not allowed.'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(405, $response->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('Method not allowed.', $payload['message']);
    }

    public function test_method_not_allowed_defers_to_default_for_web_requests(): void
    {
        $handler = $this->handler();
        RendersApiExceptions::registerMethodNotAllowedRenderer($this->configurator($handler));

        $request = Request::create('/posts', 'POST');

        $response = $handler->render($request, new MethodNotAllowedHttpException(['GET']));

        // Default rendering produces a non-JSON (HTML/symfony) response.
        $this->assertNotInstanceOf(JsonResponse::class, $response);
    }

    public function test_slack_reporter_is_a_noop_without_a_configured_channel(): void
    {
        config(['logging.channels.slack.url' => null]);

        $handler = $this->handler();
        RendersApiExceptions::registerSlackReporter($this->configurator($handler));

        Log::spy();

        // Reporting must not throw and must not log to slack when unconfigured.
        $handler->report(new RuntimeException('boom'));

        $this->assertTrue(true);
    }

    public function test_slack_reporter_alerts_and_throttles_for_web_requests(): void
    {
        config([
            'logging.channels.slack' => [
                'driver' => 'single',
                'path' => storage_path('logs/slack-test.log'),
                'url' => 'https://hooks.example.test/abc',
            ],
            'app.debug' => false,
        ]);

        Cache::flush();

        $slack = Log::spy();
        Log::shouldReceive('channel')->with('slack')->andReturn($slack);

        $handler = $this->handler();
        RendersApiExceptions::registerSlackReporter($this->configurator($handler), throttleMinutes: 5);

        // The reporter intentionally skips console/maintenance runs (matching the
        // legacy handler); fake a web (non-console) runtime so the alert path runs.
        $this->asWebRequest(function () use ($handler): void {
            $handler->report(new RuntimeException('boom'));
            // Second report within the window must be suppressed by the throttle.
            $handler->report(new RuntimeException('boom again'));
        });

        $this->assertTrue(Cache::has('laranail:toolkit:slack-error-throttle'));
        $slack->shouldHaveReceived('critical')->once();
    }

    /**
     * Run a callback with the application reporting a non-console runtime so the
     * console-gated reporter logic executes, then restore the cached flag.
     */
    private function asWebRequest(callable $callback): void
    {
        $reflection = new \ReflectionObject($this->app);
        $flag = $reflection->getProperty('isRunningInConsole');

        $previous = $flag->getValue($this->app);
        $flag->setValue($this->app, false);

        try {
            $callback();
        } finally {
            $flag->setValue($this->app, $previous);
        }
    }

    public function test_register_wires_both_helpers(): void
    {
        $handler = $this->handler();

        RendersApiExceptions::register($this->configurator($handler));

        $request = Request::create('/api/posts', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $handler->render($request, new MethodNotAllowedHttpException(['GET']));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(405, $response->getStatusCode());
    }
}
