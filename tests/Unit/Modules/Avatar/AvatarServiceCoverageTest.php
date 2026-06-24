<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Avatar;

use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarFont;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolution;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarService;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Exercises the less-trodden AvatarService paths: resolution helpers, the
 * Gravatar-integration surface, font selection, image objects, and the
 * fallback avatar. Image output is asserted structurally (PNG header / size),
 * never pixel-by-pixel.
 */
class AvatarServiceCoverageTest extends TestCase
{
    private function service(): AvatarService
    {
        $service = $this->app->make(AvatarServiceInterface::class);
        $this->assertInstanceOf(AvatarService::class, $service);

        return $service;
    }

    public function test_generate_alias_returns_a_png_data_uri(): void
    {
        $uri = $this->service()->setName('Al Pha')->setSize(40, 40)->setCacheEnabled(false)->generate();

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_get_image_object_returns_an_image_of_the_requested_size(): void
    {
        $image = $this->service()->setName('Box')->setSize(72, 60)->getImageObject();

        $this->assertSame(72, $image->width());
        $this->assertSame(60, $image->height());
    }

    public function test_make_initials_without_a_name_returns_a_single_uppercase_letter(): void
    {
        $initial = $this->service()->makeInitials();

        $this->assertSame(1, strlen($initial));
        $this->assertMatchesRegularExpression('/^[A-Z]$/', $initial);
    }

    public function test_simple_setters_and_getters_round_trip(): void
    {
        $service = $this->service()
            ->setWidth(120)
            ->setHeight(140)
            ->setChars(3)
            ->setFontSize(64)
            ->setBorderSize(5)
            ->setCacheTtl(99)
            ->setAscii(true);

        $this->assertSame(120, $service->getWidth());
        $this->assertSame(140, $service->getHeight());
        $this->assertSame(3, $service->getChars());
        $this->assertSame(64, $service->getFontSize());
        $this->assertSame(5, $service->getBorderSize());
        $this->assertSame(99, $service->getCacheTtl());
        $this->assertTrue($service->isAscii());
        $this->assertTrue($service->isCacheEnabled());
    }

    public function test_is_image_processing_available_reports_gd_or_imagick(): void
    {
        $this->assertSame(
            extension_loaded('gd') || extension_loaded('imagick'),
            $this->service()->isImageProcessingAvailable(),
        );
    }

    public function test_available_lists_expose_shapes_and_colors(): void
    {
        $service = $this->service();

        $this->assertSame(['circle', 'square'], $service->getAvailableShapes());
        $this->assertContains('#FFFFFF', $service->getAvailableForegroundColors());
        $this->assertNotEmpty($service->getAvailableBackgroundColors());
    }

    public function test_font_enum_helpers_expose_the_catalogue(): void
    {
        $service = $this->service();

        $this->assertContains('Roboto-Bold.ttf', $service->getAvailableFontValues());
        $this->assertContains('ROBOTO_BOLD', $service->getAvailableFontNames());
        $this->assertContains(AvatarFont::ROBOTO_BOLD, $service->getAvailableFontEnums());
    }

    public function test_set_default_font_name_accepts_known_and_rejects_unknown(): void
    {
        $service = $this->service()->setDefaultFontName('FreeSerif.ttf');
        $this->assertSame('FreeSerif.ttf', $service->getDefaultFontName());

        $this->expectException(InvalidArgumentException::class);
        $this->service()->setDefaultFontName('Nope.ttf');
    }

    public function test_use_font_points_font_path_at_the_bundled_file(): void
    {
        $service = $this->service()->useFont(AvatarFont::FREE_SERIF);

        $this->assertStringEndsWith('FreeSerif.ttf', $service->getFontPath());
    }

    public function test_use_font_by_name_accepts_known_and_rejects_unknown(): void
    {
        $service = $this->service()->useFontByName('Roboto-Bold.ttf');
        $this->assertStringEndsWith('Roboto-Bold.ttf', $service->getFontPath());

        $this->expectException(InvalidArgumentException::class);
        $this->service()->useFontByName('Made-Up.ttf');
    }

    public function test_use_default_font_resolves_a_bundled_path(): void
    {
        $service = $this->service()->useFont(AvatarFont::FREE_SERIF)->useDefaultFont();

        $this->assertStringEndsWith('Roboto-Bold.ttf', $service->getFontPath());
    }

    public function test_set_font_path_rejects_a_missing_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->setFontPath('/no/such/font.ttf');
    }

    public function test_gravatar_fluent_builder_uses_email_from_name(): void
    {
        $builder = $this->service()->setName('person@example.com')->gravatar();

        $this->assertInstanceOf(GravatarServiceInterface::class, $builder);
        $this->assertStringContainsString(md5('person@example.com'), $builder->generate());
    }

    public function test_gravatar_fluent_builder_accepts_explicit_email(): void
    {
        $builder = $this->service()->gravatar('explicit@example.com');

        $this->assertStringContainsString(md5('explicit@example.com'), $builder->generate());
    }

    public function test_gravatar_fluent_builder_without_email_returns_bare_builder(): void
    {
        $builder = $this->service()->setName('No Email Here')->gravatar();

        $this->assertInstanceOf(GravatarServiceInterface::class, $builder);
        $this->assertNull($builder->getEmail());
    }

    public function test_has_gravatar_reflects_email_presence(): void
    {
        $this->assertTrue($this->service()->setName('valid@example.com')->hasGravatar());
        $this->assertFalse($this->service()->setName('Just A Name')->hasGravatar());
    }

    public function test_generate_with_gravatar_fallback_prefers_gravatar_when_email_present(): void
    {
        $result = $this->service()->setName('user@example.com')->generateWithGravatarFallback(96, true);

        $this->assertStringContainsString('gravatar.com/avatar/', $result);
    }

    public function test_generate_with_gravatar_fallback_renders_avatar_without_email(): void
    {
        $result = $this->service()->setName('Jane Doe')->generateWithGravatarFallback(50, true);

        $this->assertStringStartsWith('data:image/png;base64,', $result);
    }

    public function test_gravatar_rating_and_default_image_lists_are_exposed(): void
    {
        $service = $this->service();

        $this->assertContains('g', $service->getGravatarRatings());
        $this->assertContains('monsterid', $service->getGravatarDefaultImages());
    }

    public function test_get_avatar_resolves_an_email_string_to_a_gravatar(): void
    {
        $resolution = $this->service()->getAvatar('hello@example.com');

        $this->assertInstanceOf(AvatarResolution::class, $resolution);
        $this->assertStringContainsString('gravatar.com/avatar/', $resolution->getUrl());
    }

    public function test_get_avatar_url_returns_the_resolved_url_string(): void
    {
        $url = $this->service()->getAvatarUrl('hello@example.com');

        $this->assertStringContainsString('gravatar.com/avatar/', $url);
    }

    public function test_get_avatar_cached_returns_resolution_and_caches_it(): void
    {
        $service = $this->service();

        $first = $service->getAvatarCached('hello@example.com', ttl: 60);
        $second = $service->getAvatarCached('hello@example.com', ttl: 60);

        $this->assertInstanceOf(AvatarResolution::class, $first);
        $this->assertSame($first->getUrl(), $second->getUrl());
    }

    public function test_get_avatar_accepts_a_callable_source(): void
    {
        $resolution = $this->service()->getAvatar(static fn (): string => 'callback@example.com');

        $this->assertInstanceOf(AvatarResolution::class, $resolution);
    }
}
