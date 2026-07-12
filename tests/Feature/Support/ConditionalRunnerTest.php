<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Support\ConditionalRunner;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('support')]
class ConditionalRunnerTest extends TestCase
{
    public function test_make_returns_a_fresh_runner(): void
    {
        $this->assertInstanceOf(ConditionalRunner::class, ConditionalRunner::make());
    }

    public function test_when_console_runs_under_console_context(): void
    {
        // Testbench boots in the console, so runningInConsole() is true.
        $results = ConditionalRunner::make()
            ->whenConsole(fn (): string => 'ran')
            ->run();

        $this->assertSame(['ran'], $results);
    }

    public function test_when_not_console_is_skipped_under_console_context(): void
    {
        $results = ConditionalRunner::make()
            ->whenNotConsole(fn (): string => 'ran')
            ->run();

        $this->assertSame([], $results);
    }

    public function test_when_api_runs_for_api_path(): void
    {
        $this->bindRequest(Request::create('/api/widgets', 'GET'));

        $results = ConditionalRunner::make()
            ->whenApi(fn (): string => 'api')
            ->run();

        $this->assertSame(['api'], $results);
    }

    public function test_when_api_runs_when_json_is_expected(): void
    {
        $this->bindRequest(Request::create('/dashboard', 'GET', server: ['HTTP_ACCEPT' => 'application/json']));

        $results = ConditionalRunner::make()
            ->whenApi(fn (): string => 'api')
            ->run();

        $this->assertSame(['api'], $results);
    }

    public function test_when_api_is_skipped_for_a_plain_web_request(): void
    {
        $this->bindRequest(Request::create('/dashboard', 'GET'));

        $results = ConditionalRunner::make()
            ->whenApi(fn (): string => 'api')
            ->run();

        $this->assertSame([], $results);
    }

    public function test_when_web_runs_for_a_plain_web_request(): void
    {
        $this->bindRequest(Request::create('/dashboard', 'GET'));

        $results = ConditionalRunner::make()
            ->whenWeb(fn (): string => 'web')
            ->run();

        $this->assertSame(['web'], $results);
    }

    public function test_when_web_is_skipped_for_an_api_request(): void
    {
        $this->bindRequest(Request::create('/api/widgets', 'GET'));

        $results = ConditionalRunner::make()
            ->whenWeb(fn (): string => 'web')
            ->run();

        $this->assertSame([], $results);
    }

    public function test_when_authenticated_runs_only_when_a_user_is_logged_in(): void
    {
        $guest = ConditionalRunner::make()
            ->whenAuthenticated(fn (): string => 'auth')
            ->run();

        $this->assertSame([], $guest);

        $this->actingAs(new ConditionalRunnerUser());

        $authed = ConditionalRunner::make()
            ->whenAuthenticated(fn (): string => 'auth')
            ->run();

        $this->assertSame(['auth'], $authed);
    }

    public function test_when_guest_runs_only_for_guests(): void
    {
        $guest = ConditionalRunner::make()
            ->whenGuest(fn (): string => 'guest')
            ->run();

        $this->assertSame(['guest'], $guest);

        $this->actingAs(new ConditionalRunnerUser());

        $authed = ConditionalRunner::make()
            ->whenGuest(fn (): string => 'guest')
            ->run();

        $this->assertSame([], $authed);
    }

    public function test_when_role_uses_the_user_has_role_method(): void
    {
        $this->actingAs(new ConditionalRunnerUser(['admin']));

        $matched = ConditionalRunner::make()
            ->whenRole('admin', fn (): string => 'yes')
            ->run();

        $this->assertSame(['yes'], $matched);

        $missed = ConditionalRunner::make()
            ->whenRole('editor', fn (): string => 'yes')
            ->run();

        $this->assertSame([], $missed);
    }

    public function test_when_role_is_false_for_a_model_without_has_role(): void
    {
        $this->actingAs(new ConditionalRunnerRolelessUser());

        $results = ConditionalRunner::make()
            ->whenRole('admin', fn (): string => 'yes')
            ->run();

        $this->assertSame([], $results);
    }

    public function test_when_role_is_false_for_guests(): void
    {
        $results = ConditionalRunner::make()
            ->whenRole('admin', fn (): string => 'yes')
            ->run();

        $this->assertSame([], $results);
    }

    public function test_when_role_is_not_runs_when_role_absent(): void
    {
        $this->actingAs(new ConditionalRunnerUser(['editor']));

        $matched = ConditionalRunner::make()
            ->whenRoleIsNot('admin', fn (): string => 'no-admin')
            ->run();

        $this->assertSame(['no-admin'], $matched);

        $skipped = ConditionalRunner::make()
            ->whenRoleIsNot('editor', fn (): string => 'no-editor')
            ->run();

        $this->assertSame([], $skipped);
    }

    public function test_when_role_is_not_is_false_without_a_resolvable_user(): void
    {
        // Guest: cannot prove role absence on a real model, so skip.
        $guest = ConditionalRunner::make()
            ->whenRoleIsNot('admin', fn (): string => 'no-admin')
            ->run();

        $this->assertSame([], $guest);

        // Model without hasRole(): role is unverifiable, so skip.
        $this->actingAs(new ConditionalRunnerRolelessUser());

        $roleless = ConditionalRunner::make()
            ->whenRoleIsNot('admin', fn (): string => 'no-admin')
            ->run();

        $this->assertSame([], $roleless);
    }

    public function test_predicates_chain_and_each_fires_independently(): void
    {
        $this->bindRequest(Request::create('/api/widgets', 'GET'));
        $this->actingAs(new ConditionalRunnerUser(['admin']));

        $results = ConditionalRunner::make()
            ->whenApi(fn (): string => 'api')
            ->whenWeb(fn (): string => 'web')        // skipped — request is API
            ->whenAuthenticated(fn (): string => 'auth')
            ->whenRole('admin', fn (): string => 'admin')
            ->whenRole('editor', fn (): string => 'editor') // skipped — role absent
            ->run();

        $this->assertSame(['api', 'auth', 'admin'], $results);
    }

    public function test_run_clears_pending_callbacks(): void
    {
        $runner = ConditionalRunner::make()->when(true, fn (): string => 'once');

        $this->assertSame(['once'], $runner->run());
        $this->assertSame([], $runner->run());
    }

    /**
     * Bind a request instance so the API/web predicates read it.
     */
    private function bindRequest(Request $request): void
    {
        $this->app->instance('request', $request);
    }
}

class ConditionalRunnerUser extends Authenticatable
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

class ConditionalRunnerRolelessUser extends Authenticatable {}
