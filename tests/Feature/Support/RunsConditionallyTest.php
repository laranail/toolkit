<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Support\ConditionalRunner;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\RunsConditionally;

#[Group('support')]
class RunsConditionallyTest extends TestCase
{
    private RunsConditionallyHost $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = new RunsConditionallyHost();
    }

    public function test_conditional_exposes_a_runner(): void
    {
        $this->assertInstanceOf(ConditionalRunner::class, $this->host->conditionalRunner());
    }

    public function test_run_in_console_returns_the_callback_result(): void
    {
        $this->assertSame('console', $this->host->inConsole(fn (): string => 'console'));
    }

    public function test_run_outside_console_returns_null_under_console(): void
    {
        $this->assertNull($this->host->outsideConsole(fn (): string => 'web'));
    }

    public function test_run_when_authenticated_gates_on_auth_state(): void
    {
        $this->assertNull($this->host->whenAuthed(fn (): string => 'auth'));

        $this->actingAs(new RunsConditionallyUser());

        $this->assertSame('auth', $this->host->whenAuthed(fn (): string => 'auth'));
    }

    public function test_run_when_guest_gates_on_auth_state(): void
    {
        $this->assertSame('guest', $this->host->whenGuest(fn (): string => 'guest'));

        $this->actingAs(new RunsConditionallyUser());

        $this->assertNull($this->host->whenGuest(fn (): string => 'guest'));
    }

    public function test_run_for_role_delegates_to_has_role(): void
    {
        $this->actingAs(new RunsConditionallyUser(['admin']));

        $this->assertSame('ok', $this->host->forRole('admin', fn (): string => 'ok'));
        $this->assertNull($this->host->forRole('editor', fn (): string => 'ok'));
    }

    public function test_run_for_web_fires_only_for_a_bound_non_api_request(): void
    {
        $this->app->instance('request', Request::create('/dashboard', 'GET'));

        $this->assertSame('web', $this->host->forWeb(fn (): string => 'web'));
        // A plain web request is not an API request.
        $this->assertNull($this->host->forApi(fn (): string => 'api'));
    }

    public function test_run_for_api_fires_for_an_api_path_request(): void
    {
        $this->app->instance('request', Request::create('/api/users', 'GET'));

        $this->assertSame('api', $this->host->forApi(fn (): string => 'api'));
        // An API request is not classified as a web request.
        $this->assertNull($this->host->forWeb(fn (): string => 'web'));
    }

    public function test_run_for_api_fires_when_the_request_expects_json(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $request->headers->set('Accept', 'application/json');
        $this->app->instance('request', $request);

        $this->assertSame('json', $this->host->forApi(fn (): string => 'json'));
    }
}

/**
 * Concrete host exercising the {@see RunsConditionally} trait's protected API.
 */
class RunsConditionallyHost
{
    use RunsConditionally;

    public function conditionalRunner(): ConditionalRunner
    {
        return $this->conditional();
    }

    public function inConsole(callable $callback): mixed
    {
        return $this->runInConsole($callback);
    }

    public function outsideConsole(callable $callback): mixed
    {
        return $this->runOutsideConsole($callback);
    }

    public function whenAuthed(callable $callback): mixed
    {
        return $this->runWhenAuthenticated($callback);
    }

    public function whenGuest(callable $callback): mixed
    {
        return $this->runWhenGuest($callback);
    }

    public function forRole(string $role, callable $callback): mixed
    {
        return $this->runForRole($role, $callback);
    }

    public function forWeb(callable $callback): mixed
    {
        return $this->runForWeb($callback);
    }

    public function forApi(callable $callback): mixed
    {
        return $this->runForApi($callback);
    }
}

class RunsConditionallyUser extends Authenticatable
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly array $roles = [],
    ) {
        parent::__construct();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
