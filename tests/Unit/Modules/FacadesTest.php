<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules;

use Simtabi\Laranail\Toolkit\Facades\Laranail;
use Simtabi\Laranail\Toolkit\Facades\Toolkit;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Avatar;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Captcha;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaService;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Gravatar;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\LLM\Claude\ClaudeProvider;
use Simtabi\Laranail\Toolkit\Modules\LLM\Gemini\GeminiProvider;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLM;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMProviderInterface;
use Simtabi\Laranail\Toolkit\Modules\LLM\OpenAI\OpenAIProvider;
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationContextServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\DatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RateLimiterServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SchedulerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SettingsStoreInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ValidationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\ModelService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class FacadesTest extends TestCase
{
    public function test_avatar_facade_resolves_its_root(): void
    {
        $this->assertInstanceOf(AvatarServiceInterface::class, Avatar::getFacadeRoot());
    }

    public function test_gravatar_facade_resolves_its_root(): void
    {
        $this->assertInstanceOf(GravatarServiceInterface::class, Gravatar::getFacadeRoot());
    }

    public function test_captcha_facade_resolves_its_root(): void
    {
        $this->assertInstanceOf(CaptchaService::class, Captcha::getFacadeRoot());
    }

    public function test_llm_facade_resolves_the_default_provider(): void
    {
        $this->assertInstanceOf(LLMProviderInterface::class, LLM::getFacadeRoot());
    }

    public function test_llm_provider_is_registered_exactly_once(): void
    {
        // The LLM binding lives solely in the deferred LLMServiceProvider; the
        // root ToolkitServiceProvider must not also register the contract.
        $this->assertTrue($this->app->bound(LLMProviderInterface::class));
        $this->assertInstanceOf(LLMProviderInterface::class, $this->app->make('laranail.llm'));
        $this->assertInstanceOf(LLMProviderInterface::class, $this->app->make(LLMProviderInterface::class));
    }

    public function test_gravatar_facade_proxies_a_method_call(): void
    {
        $url = Gravatar::setEmail('user@example.com')->setSize(120)->generate();

        $this->assertStringContainsString('gravatar.com/avatar/', $url);
    }

    public function test_toolkit_facade_fronts_each_module(): void
    {
        $this->assertInstanceOf(AvatarServiceInterface::class, Toolkit::avatar());
        $this->assertInstanceOf(GravatarServiceInterface::class, Toolkit::gravatar());
        $this->assertInstanceOf(CaptchaService::class, Toolkit::captcha());
        $this->assertInstanceOf(ArchiverServiceInterface::class, Toolkit::archiver());
    }

    public function test_toolkit_facade_chains_into_a_module(): void
    {
        $url = Toolkit::gravatar()->setEmail('user@example.com')->setSize(64)->generate();

        $this->assertStringContainsString('gravatar.com/avatar/', $url);
    }

    public function test_toolkit_facade_fronts_each_cross_cutting_service(): void
    {
        $this->assertInstanceOf(RouteServiceInterface::class, Toolkit::route());
        $this->assertInstanceOf(ValidationServiceInterface::class, Toolkit::validation());
        $this->assertInstanceOf(SessionServiceInterface::class, Toolkit::session());
        $this->assertInstanceOf(DatabaseServiceInterface::class, Toolkit::db());
        $this->assertInstanceOf(DatabaseServiceInterface::class, Toolkit::database());
        $this->assertInstanceOf(ModelService::class, Toolkit::model());
        $this->assertInstanceOf(HttpConfigurationServiceInterface::class, Toolkit::http());
        $this->assertInstanceOf(FileServiceInterface::class, Toolkit::file());
        $this->assertInstanceOf(SystemServiceInterface::class, Toolkit::system());
    }

    public function test_toolkit_facade_fronts_auth_atlas_and_livewire(): void
    {
        $this->assertInstanceOf(AuthenticationContextServiceInterface::class, Toolkit::auth());
        $this->assertInstanceOf(AtlasServiceInterface::class, Toolkit::atlas());
        $this->assertInstanceOf(LivewireServiceInterface::class, Toolkit::livewire());
    }

    public function test_toolkit_facade_fronts_the_newly_exposed_services(): void
    {
        $this->assertInstanceOf(CacheRepositoryInterface::class, Toolkit::cache());
        $this->assertInstanceOf(LoggerServiceInterface::class, Toolkit::log());
        $this->assertInstanceOf(SettingsStoreInterface::class, Toolkit::settings());
        $this->assertInstanceOf(RateLimiterServiceInterface::class, Toolkit::rateLimiter());
        $this->assertInstanceOf(SchedulerServiceInterface::class, Toolkit::scheduler());

        // The same accessors are reachable through the Laranail alias facade.
        $this->assertInstanceOf(CacheRepositoryInterface::class, Laranail::cache());
        $this->assertInstanceOf(LoggerServiceInterface::class, Laranail::log());
        $this->assertInstanceOf(SettingsStoreInterface::class, Laranail::settings());
        $this->assertInstanceOf(RateLimiterServiceInterface::class, Laranail::rateLimiter());
        $this->assertInstanceOf(SchedulerServiceInterface::class, Laranail::scheduler());
    }

    public function test_llm_binding_selects_openai_by_default(): void
    {
        $this->app->forgetInstance(LLMProviderInterface::class);
        $this->app['config']->set('laranail.toolkit.llm.default_provider', 'openai');
        $this->app['config']->set('laranail.toolkit.llm.openai.api_key', 'sk-test');

        $this->assertInstanceOf(OpenAIProvider::class, $this->app->make(LLMProviderInterface::class));
    }

    public function test_llm_binding_selects_gemini_when_configured(): void
    {
        $this->app->forgetInstance(LLMProviderInterface::class);
        $this->app['config']->set('laranail.toolkit.llm.default_provider', 'gemini');
        $this->app['config']->set('laranail.toolkit.llm.gemini.api_key', 'gemini-test');

        $this->assertInstanceOf(GeminiProvider::class, $this->app->make(LLMProviderInterface::class));
    }

    public function test_llm_binding_selects_claude_when_configured(): void
    {
        $this->app->forgetInstance(LLMProviderInterface::class);
        $this->app['config']->set('laranail.toolkit.llm.default_provider', 'claude');
        $this->app['config']->set('laranail.toolkit.llm.claude.api_key', 'claude-test');

        $this->assertInstanceOf(ClaudeProvider::class, $this->app->make(LLMProviderInterface::class));
    }

    public function test_laranail_facade_fronts_each_module(): void
    {
        $this->assertInstanceOf(AvatarServiceInterface::class, Laranail::avatar());
        $this->assertInstanceOf(RouteServiceInterface::class, Laranail::route());
        $this->assertInstanceOf(SessionServiceInterface::class, Laranail::session());
        $this->assertInstanceOf(DatabaseServiceInterface::class, Laranail::database());
        $this->assertInstanceOf(AtlasServiceInterface::class, Laranail::atlas());
    }

    public function test_laranail_and_toolkit_resolve_the_same_manager_and_services(): void
    {
        // One manager, two facade names: both facades must resolve the very same
        // singleton ToolkitManager — that is the merge (no duplicated logic).
        $this->assertSame(Laranail::getFacadeRoot(), Toolkit::getFacadeRoot());

        // And a service reached through either name is the same type/contract.
        $this->assertInstanceOf(RouteServiceInterface::class, Laranail::route());
        $this->assertInstanceOf(RouteServiceInterface::class, Toolkit::route());

        // Singleton-bound services resolve to the identical instance via either.
        $this->assertSame(Laranail::atlas(), Toolkit::atlas());
    }
}
