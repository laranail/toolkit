<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasAvatar;

class AvatarMember extends Model
{
    use HasAvatar;

    protected $table = 'avatar_members';

    public $timestamps = false;

    protected $guarded = [];
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
}
