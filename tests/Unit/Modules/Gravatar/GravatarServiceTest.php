<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Gravatar;

use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarService;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class GravatarServiceTest extends TestCase
{
    private function service(): GravatarService
    {
        return new GravatarService();
    }

    public function test_it_resolves_from_the_container_via_the_contract(): void
    {
        $this->assertInstanceOf(
            GravatarService::class,
            $this->app->make(GravatarServiceInterface::class),
        );
    }

    public function test_it_hashes_the_normalized_email(): void
    {
        // Canonical Gravatar example: trimmed + lowercased before md5.
        $expected = md5('myemailaddress@example.com');

        $this->assertSame($expected, $this->service()->hashEmail('  MyEmailAddress@example.com '));
    }

    public function test_it_defaults_to_https(): void
    {
        $url = $this->service()->setEmail('user@example.com')->generate();

        $this->assertStringStartsWith('https://secure.gravatar.com/avatar/', $url);
    }

    public function test_generate_includes_size_rating_and_default(): void
    {
        $url = $this->service()
            ->setEmail('user@example.com')
            ->setSize(120)
            ->setRating('pg')
            ->setDefaultImage('retro')
            ->generate();

        $this->assertStringContainsString('s=120', $url);
        $this->assertStringContainsString('r=pg', $url);
        $this->assertStringContainsString('d=retro', $url);
    }

    public function test_valid_rating_and_default_are_accepted(): void
    {
        // Regression: the legacy Arr::has() bug made every valid value throw.
        $service = $this->service()->setRating('r')->setDefaultImage('identicon');

        $this->assertSame('r', $service->getRating());
        $this->assertSame('identicon', $service->getDefaultImage());
    }

    public function test_invalid_rating_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->setRating('zzz');
    }

    public function test_size_is_clamped_to_gravatar_limits(): void
    {
        $this->assertSame(2048, $this->service()->setSize(99999)->getSize());
        $this->assertSame(1, $this->service()->setSize(0)->getSize());
    }

    public function test_the_builder_is_immutable(): void
    {
        $base = $this->service();
        $modified = $base->setEmail('user@example.com')->setSize(50);

        $this->assertNull($base->getEmail());
        $this->assertNotSame($base, $modified);
        $this->assertSame('user@example.com', $modified->getEmail());
    }

    public function test_generate_requires_a_valid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->setEmail('not-an-email')->generate();
    }

    public function test_resolve_returns_a_structured_dto(): void
    {
        $resolution = $this->service()->setEmail('user@example.com')->setRating('g')->resolve();

        $this->assertSame('user@example.com', $resolution->email);
        $this->assertSame(md5('user@example.com'), $resolution->hash);
        $this->assertTrue($resolution->isSecure());
        $this->assertTrue($resolution->isAppropriate());
        $this->assertSame('secure.gravatar.com', $resolution->domain());
    }

    public function test_http_base_url_is_used_when_https_disabled(): void
    {
        $url = $this->service()->setEmail('user@example.com')->setHttps(false)->generate();

        $this->assertStringStartsWith('http://www.gravatar.com/avatar/', $url);
        $this->assertFalse($this->service()->setHttps(false)->isHttps());
    }

    public function test_force_default_adds_the_f_parameter(): void
    {
        $service = $this->service()->setEmail('user@example.com')->setForceDefault(true);

        $this->assertTrue($service->isForceDefault());
        $this->assertStringContainsString('f=y', $service->generate());
    }

    public function test_custom_default_url_overrides_the_default_image(): void
    {
        $service = $this->service()
            ->setEmail('user@example.com')
            ->setCustomDefaultUrl('https://cdn.example.com/fallback.png');

        $this->assertSame('https://cdn.example.com/fallback.png', $service->getCustomDefaultUrl());
        $this->assertStringContainsString(rawurlencode('https://cdn.example.com/fallback.png'), $service->generate());
    }

    public function test_custom_default_url_rejects_invalid_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->setCustomDefaultUrl('not a url');
    }

    public function test_custom_default_url_accepts_null_to_clear(): void
    {
        $this->assertNull($this->service()->setCustomDefaultUrl(null)->getCustomDefaultUrl());
    }

    public function test_to_string_returns_the_generated_url(): void
    {
        $service = $this->service()->setEmail('user@example.com');

        $this->assertStringContainsString('gravatar.com/avatar/', (string) $service);
    }

    public function test_to_string_is_empty_when_email_missing(): void
    {
        $this->assertSame('', (string) $this->service());
    }

    public function test_is_valid_email_validates_addresses(): void
    {
        $this->assertTrue($this->service()->isValidEmail('a@b.com'));
        $this->assertFalse($this->service()->isValidEmail('nope'));
    }

    public function test_available_ratings_and_default_images_are_exposed(): void
    {
        $service = $this->service();

        $this->assertContains('g', $service->availableRatings());
        $this->assertContains('robohash', $service->availableDefaultImages());
    }
}
