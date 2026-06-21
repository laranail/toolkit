<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Modules\Avatar;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Contracts\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\DataTransferObjects\AvatarResolution;
use Simtabi\Laranail\Toolkit\Modules\Avatar\DataTransferObjects\AvatarResolutionContextData;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class AvatarResolverTest extends TestCase
{
    private function service(): AvatarServiceInterface
    {
        return $this->app->make(AvatarServiceInterface::class);
    }

    public function test_di_resolves_the_avatar_service(): void
    {
        $this->assertInstanceOf(AvatarServiceInterface::class, $this->service());
    }

    public function test_resolve_from_a_name_string_produces_initials(): void
    {
        $resolution = $this->service()->getAvatar('Jane Doe');

        $this->assertInstanceOf(AvatarResolution::class, $resolution);
        $this->assertTrue($resolution->isFromName());
        $this->assertTrue($resolution->isInitials());
        $this->assertStringStartsWith('data:image/png;base64,', $resolution->getUrl());
    }

    public function test_resolve_from_an_email_string_produces_a_gravatar(): void
    {
        $resolution = $this->service()->getAvatar('user@example.com');

        $this->assertTrue($resolution->isFromEmail());
        $this->assertTrue($resolution->isGravatar());
        $this->assertStringContainsString('gravatar.com/avatar/', $resolution->getUrl());
    }

    public function test_resolve_from_a_model_prefers_its_stored_avatar_url(): void
    {
        $user = new ResolverTestUser([
            'name' => 'Modelled User',
            'email' => 'modelled@example.com',
            'avatar_url' => 'https://cdn.example.com/avatars/1.png',
        ]);

        $resolution = $this->service()->getAvatar($user);

        $this->assertTrue($resolution->isFromModel());
        $this->assertTrue($resolution->isUrl());
        $this->assertSame('https://cdn.example.com/avatars/1.png', $resolution->getUrl());
    }

    public function test_resolve_from_a_model_falls_back_to_gravatar_then_initials(): void
    {
        $gravatarUser = new ResolverTestUser([
            'name' => 'Gravatar User',
            'email' => 'gravatar@example.com',
        ]);

        $gravatar = $this->service()->getAvatar($gravatarUser);
        $this->assertTrue($gravatar->isGravatar());

        $initialsUser = new ResolverTestUser(['name' => 'Initials Only']);
        $initials = $this->service()->getAvatar($initialsUser, ['prefer_gravatar' => false]);

        $this->assertTrue($initials->isInitials());
        $this->assertStringStartsWith('data:image/png;base64,', $initials->getUrl());
    }

    public function test_resolve_from_a_callable_returning_a_dto(): void
    {
        $resolution = $this->service()->getAvatar(
            fn (AvatarResolutionContextData $context): AvatarResolution => $context->createGravatarResult('callback@example.com', 100),
        );

        $this->assertTrue($resolution->isFromCallback());
        $this->assertTrue($resolution->isGravatar());
        $this->assertStringContainsString('s=100', $resolution->getUrl());
    }

    public function test_resolve_from_a_callable_returning_a_string_url(): void
    {
        $resolution = $this->service()->getAvatar(
            fn (): string => 'https://example.com/custom.png',
        );

        $this->assertTrue($resolution->isFromCallback());
        $this->assertTrue($resolution->isUrl());
        $this->assertSame('https://example.com/custom.png', $resolution->getUrl());
    }

    public function test_cached_resolution_returns_the_same_result(): void
    {
        $first = $this->service()->getAvatarCached('user@example.com');
        $second = $this->service()->getAvatarCached('user@example.com');

        $this->assertSame($first->getUrl(), $second->getUrl());
    }

    public function test_get_avatar_url_returns_the_resolved_url(): void
    {
        $url = $this->service()->getAvatarUrl('user@example.com');

        $this->assertStringContainsString('gravatar.com/avatar/', $url);
    }
}

/**
 * Minimal in-memory Eloquent model used to exercise model-based resolution.
 */
class ResolverTestUser extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public $exists = true;
}
