<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasAvatar;

class AvatarMember extends Model
{
    use HasAvatar;

    protected $table = 'avatar_members';

    public $timestamps = false;

    protected $guarded = [];

    public function publicResolveAvatarName(): string
    {
        return $this->resolveAvatarName();
    }
}

#[Group('traits')]
class HasAvatarTest extends TestCase
{
    public function test_gravatar_builds_a_url_from_the_email_attribute(): void
    {
        $member = new AvatarMember(['email' => 'Person@Example.com']);

        $url = $member->gravatar(120, true);

        $this->assertNotNull($url);
        $this->assertStringContainsString('https://secure.gravatar.com/avatar/', $url);
        // Hash is of the lower-cased, trimmed email.
        $this->assertStringContainsString(md5('person@example.com'), $url);
        $this->assertStringContainsString('s=120', $url);
    }

    public function test_gravatar_is_null_without_an_email(): void
    {
        $member = new AvatarMember();

        $this->assertNull($member->gravatar());
    }

    public function test_get_avatar_falls_back_to_gravatar_when_no_stored_avatar(): void
    {
        $member = new AvatarMember(['email' => 'fallback@example.com']);

        $avatar = $member->getAvatar();

        $this->assertNotNull($avatar);
        $this->assertStringContainsString('gravatar.com/avatar/', $avatar);
    }

    public function test_get_avatar_returns_stored_value_when_present(): void
    {
        $member = new AvatarMember(['email' => 'x@example.com', 'avatar' => 'https://cdn.example.com/me.png']);

        $this->assertSame('https://cdn.example.com/me.png', $member->getAvatar());
    }

    public function test_get_avatar_is_null_when_no_avatar_and_no_email(): void
    {
        $member = new AvatarMember();

        $this->assertNull($member->getAvatar());
    }

    public function test_get_gravatar_applies_default_image_and_rating_overrides(): void
    {
        $member = new AvatarMember(['email' => 'Person@Example.com']);

        $url = $member->getGravatar(64, 'identicon', 'pg');

        $this->assertNotNull($url);
        $this->assertStringContainsString(md5('person@example.com'), $url);
        $this->assertStringContainsString('s=64', $url);
        $this->assertStringContainsString('d=identicon', $url);
        $this->assertStringContainsString('r=pg', $url);
    }

    public function test_get_gravatar_is_null_without_an_email(): void
    {
        $this->assertNull((new AvatarMember())->getGravatar());
    }

    public function test_resolve_avatar_url_returns_storage_url_for_a_managed_path(): void
    {
        Storage::fake();
        Storage::put('avatars/me.png', 'binary');

        $member = new AvatarMember();

        $this->assertSame(Storage::url('avatars/me.png'), $member->resolveAvatarUrl('avatars/me.png'));
    }

    public function test_resolve_avatar_url_returns_the_value_unchanged_for_an_unmanaged_path(): void
    {
        Storage::fake();

        $member = new AvatarMember();

        $this->assertSame('https://cdn.example.com/remote.png', $member->resolveAvatarUrl('https://cdn.example.com/remote.png'));
    }

    public function test_get_avatar_resolves_a_stored_storage_path_to_a_url(): void
    {
        Storage::fake();
        Storage::put('avatars/stored.png', 'binary');

        $member = new AvatarMember(['avatar' => 'avatars/stored.png']);

        $this->assertSame(Storage::url('avatars/stored.png'), $member->getAvatar());
    }

    public function test_generate_avatar_produces_a_png_data_uri(): void
    {
        $member = new AvatarMember(['name' => 'Jane Doe']);

        $this->assertStringStartsWith('data:image/png;base64,', $member->generateAvatar(64));
    }

    public function test_get_avatar_with_fallback_renders_initials_without_a_gravatar_email(): void
    {
        $member = new AvatarMember(['name' => 'Jane Doe']);

        $this->assertStringStartsWith('data:image/png;base64,', $member->getAvatarWithFallback(50, true));
    }

    public function test_get_avatar_with_fallback_prefers_gravatar_when_the_name_is_an_email(): void
    {
        $member = new AvatarMember(['name' => 'person@example.com']);

        $this->assertStringContainsString('gravatar.com/avatar/', $member->getAvatarWithFallback(96, true));
    }

    public function test_resolve_avatar_name_prefers_the_name_attribute(): void
    {
        $member = new AvatarMember(['name' => 'Ada Lovelace', 'username' => 'ada']);

        $this->assertSame('Ada Lovelace', $member->publicResolveAvatarName());
    }

    public function test_resolve_avatar_name_falls_back_to_first_and_last_name(): void
    {
        $member = new AvatarMember(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->assertSame('Ada Lovelace', $member->publicResolveAvatarName());
    }

    public function test_resolve_avatar_name_trims_a_missing_last_name(): void
    {
        $member = new AvatarMember(['first_name' => 'Ada']);

        $this->assertSame('Ada', $member->publicResolveAvatarName());
    }

    public function test_resolve_avatar_name_falls_back_to_username(): void
    {
        $member = new AvatarMember(['username' => 'ada']);

        $this->assertSame('ada', $member->publicResolveAvatarName());
    }

    public function test_resolve_avatar_name_falls_back_to_email(): void
    {
        $member = new AvatarMember(['email' => 'ada@example.com']);

        $this->assertSame('ada@example.com', $member->publicResolveAvatarName());
    }

    public function test_resolve_avatar_name_defaults_to_user_when_nothing_is_set(): void
    {
        $this->assertSame('User', (new AvatarMember())->publicResolveAvatarName());
    }
}
