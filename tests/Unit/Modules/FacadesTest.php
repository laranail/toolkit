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
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationHelperServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\DatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface;
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
        $this->assertInstanceOf(AuthenticationHelperServiceInterface::class, Toolkit::auth());
        $this->assertInstanceOf(AtlasServiceInterface::class, Toolkit::atlas());
        $this->assertInstanceOf(LivewireServiceInterface::class, Toolkit::livewire());
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
