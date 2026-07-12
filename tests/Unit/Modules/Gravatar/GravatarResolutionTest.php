<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Gravatar;

use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarResolution;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class GravatarResolutionTest extends TestCase
{
    public function test_secure_resolution_exposes_accessors(): void
    {
        $resolution = new GravatarResolution(
            url: 'https://secure.gravatar.com/avatar/abc',
            email: 'user@example.com',
            hash: 'abc',
            size: 200,
            isHttps: true,
            rating: 'g',
            defaultImage: 'mp',
        );

        $this->assertTrue($resolution->isSecure());
        $this->assertTrue($resolution->isAppropriate());
        $this->assertSame('secure.gravatar.com', $resolution->domain());
        $this->assertSame('https://secure.gravatar.com/avatar/abc', (string) $resolution);

        $array = $resolution->toArray();
        $this->assertSame('user@example.com', $array['email']);
        $this->assertSame('abc', $array['hash']);
        $this->assertSame(200, $array['size']);
        $this->assertTrue($array['is_https']);
        $this->assertSame('g', $array['rating']);
        $this->assertSame('mp', $array['default_image']);
        $this->assertSame('secure.gravatar.com', $array['domain']);
        $this->assertTrue($array['is_appropriate']);
    }

    public function test_insecure_and_inappropriate_resolution(): void
    {
        $resolution = new GravatarResolution(
            url: 'http://www.gravatar.com/avatar/xyz',
            email: 'a@b.com',
            hash: 'xyz',
            size: 80,
            isHttps: false,
            rating: 'x',
            defaultImage: '404',
        );

        $this->assertFalse($resolution->isSecure());
        $this->assertFalse($resolution->isAppropriate());
        $this->assertSame('www.gravatar.com', $resolution->domain());
    }

    public function test_pg_rating_is_appropriate(): void
    {
        $resolution = new GravatarResolution('u', 'e', 'h', 100, true, 'pg', 'mp');

        $this->assertTrue($resolution->isAppropriate());
    }
}
