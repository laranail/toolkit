<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Avatar;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;
use ReflectionProperty;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarFont;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolution;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarService;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Targets the less-trodden AvatarService branches: success returns of the
 * fluent setters/getters, the font-resolution fallbacks, the Throwable
 * generation/save catch paths, the default-avatar PNG and SVG fallbacks, the
 * e-mail extraction edge cases, the shape guard, and the resolution cache keys
 * for model and callable sources.
 */
class AvatarServiceBranchesTest extends TestCase
{
    private function service(): AvatarService
    {
        $service = $this->app->make(AvatarServiceInterface::class);
        $this->assertInstanceOf(AvatarService::class, $service);

        return $service;
    }

    private function fs(): Filesystem
    {
        return $this->app->make(Filesystem::class);
    }

    private function grav(): GravatarServiceInterface
    {
        return $this->app->make(GravatarServiceInterface::class);
    }

    private function cacheStore(): CacheRepository
    {
        return $this->app->make(CacheRepository::class);
    }

    // -----------------------------------------------------------------------
    // Setter/getter success returns
    // -----------------------------------------------------------------------

    public function test_set_font_path_accepts_an_existing_file(): void
    {
        $bundled = $this->service()->getAvailableFonts()['Roboto-Bold.ttf'];

        $service = $this->service()->setFontPath($bundled);

        $this->assertSame($bundled, $service->getFontPath());
    }

    public function test_get_name_returns_the_configured_name(): void
    {
        $this->assertSame('Jane Doe', $this->service()->setName('Jane Doe')->getName());
        $this->assertNull($this->service()->getName());
    }

    public function test_is_uppercase_reflects_the_flag(): void
    {
        $this->assertTrue($this->service()->setUppercase(true)->isUppercase());
        $this->assertFalse($this->service()->setUppercase(false)->isUppercase());
    }

    public function test_initials_for_a_single_word_shorter_than_char_count_returns_the_whole_word(): void
    {
        // Single word whose length (2) is below the requested char count (5):
        // the substring branch is skipped in favour of the full word.
        $this->assertSame('Al', $this->service()->setName('Al')->setChars(5)->makeInitials());
    }

    // -----------------------------------------------------------------------
    // Font-resolution fallbacks
    // -----------------------------------------------------------------------

    public function test_use_font_falls_back_to_the_default_font_when_files_are_missing(): void
    {
        $service = new FontMissingAvatarService($this->app, $this->fs(), $this->grav(), $this->cacheStore());
        $service->missing = ['FreeSerif.ttf'];

        // The requested font resolves to no on-disk file, so useFont() delegates
        // to useDefaultFont(), which lands on the bundled Roboto-Bold.
        $service->useFont(AvatarFont::FREE_SERIF);

        $this->assertStringEndsWith('Roboto-Bold.ttf', $service->getFontPath());
    }

    public function test_use_default_font_falls_back_to_default_font_path_resolution(): void
    {
        $service = new FontMissingAvatarService($this->app, $this->fs(), $this->grav(), $this->cacheStore());
        // The default font (Roboto-Bold) is missing, so useDefaultFont() falls
        // through to getDefaultFontPath(), whose catalogue scan lands on the next
        // bundled font that does exist (FreeSerif).
        $service->missing = ['Roboto-Bold.ttf'];

        $service->useDefaultFont();

        $this->assertStringEndsWith('FreeSerif.ttf', $service->getFontPath());
    }

    public function test_set_font_by_name_throws_when_no_font_file_is_found(): void
    {
        $service = new FontMissingAvatarService($this->app, $this->fs(), $this->grav(), $this->cacheStore());
        $service->missing = AvatarFont::values();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('files not found in any location');

        $service->setFontByName('FreeSerif.ttf');
    }

    public function test_constructing_with_no_resolvable_font_throws(): void
    {
        $files = new class() extends Filesystem
        {
            #[\Override]
            public function exists($path): bool
            {
                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No suitable font found');

        new AvatarService($this->app, $files, $this->grav(), $this->cacheStore());
    }

    // -----------------------------------------------------------------------
    // Throwable generation / save / default-avatar fallbacks
    // -----------------------------------------------------------------------

    public function test_get_image_object_throws_without_image_processing(): void
    {
        $service = new NoImageAvatarService($this->app, $this->fs(), $this->grav(), $this->cacheStore());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Image processing is not available');

        $service->getImageObject();
    }

    public function test_generate_falls_back_to_a_default_png_avatar_on_failure(): void
    {
        $service = new NoImageAvatarService($this->app, $this->fs(), $this->grav(), $this->cacheStore());

        $result = $service->setName('Jane')->setCacheEnabled(false)->generateBase64();

        $this->assertStringStartsWith('data:image/png;base64,', $result);
    }

    public function test_generate_falls_back_to_an_svg_avatar_when_even_the_canvas_fails(): void
    {
        $service = new BrokenImageAvatarService($this->app, $this->fs(), $this->grav(), $this->cacheStore());

        $result = $service->setName('Jane')->setSize(40, 40)->setCacheEnabled(false)->generateBase64();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $result);

        $svg = (string) base64_decode(substr($result, strlen('data:image/svg+xml;base64,')), true);
        $this->assertStringContainsString('<svg width="40" height="40"', $svg);
    }

    public function test_save_creates_the_target_directory_when_missing(): void
    {
        $dir = sys_get_temp_dir() . '/laranail_avatar_branch_' . uniqid();
        $path = $dir . '/nested/avatar.png';

        try {
            $ok = $this->service()->setName('Jane')->setSize(32, 32)->save($path);

            $this->assertTrue($ok);
            $this->assertFileExists($path);
        } finally {
            @unlink($path);
            @rmdir($dir . '/nested');
            @rmdir($dir);
        }
    }

    public function test_save_returns_false_when_image_creation_fails(): void
    {
        $service = new NoImageAvatarService($this->app, $this->fs(), $this->grav(), $this->cacheStore());

        $path = sys_get_temp_dir() . '/laranail_avatar_fail_' . uniqid() . '.png';

        $this->assertFalse($service->setName('Jane')->save($path));
        $this->assertFileDoesNotExist($path);
    }

    // -----------------------------------------------------------------------
    // Border colour + shape guard
    // -----------------------------------------------------------------------

    public function test_border_color_background_keyword_resolves_to_the_background(): void
    {
        $uri = $this->service()
            ->setName('Box')
            ->setBorderColor('background')
            ->setBorderSize(3)
            ->setSize(48, 48)
            ->setCacheEnabled(false)
            ->generate();

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_create_shape_rejects_an_unsupported_shape(): void
    {
        $service = $this->service();

        // Force an unsupported shape past the validating setter so the shape
        // dispatch hits its defensive default arm.
        $shape = new ReflectionProperty(AvatarService::class, 'shape');
        $shape->setValue($service, 'triangle');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Shape 'triangle' is not supported");

        $service->getImageObject();
    }

    // -----------------------------------------------------------------------
    // E-mail extraction from the name
    // -----------------------------------------------------------------------

    public function test_has_gravatar_is_false_when_no_name_is_set(): void
    {
        $this->assertFalse($this->service()->hasGravatar());
    }

    public function test_gravatar_extracts_an_email_from_angle_bracket_form(): void
    {
        $url = $this->service()->setName('Jane Doe <jane@example.com>')->getGravatar();

        $this->assertNotNull($url);
        $this->assertStringContainsString(md5('jane@example.com'), $url);
    }

    public function test_gravatar_returns_null_for_an_invalid_angle_bracket_email(): void
    {
        $this->assertNull($this->service()->setName('Jane Doe <not-an-email>')->getGravatar());
    }

    // -----------------------------------------------------------------------
    // Resolution options + cache keys
    // -----------------------------------------------------------------------

    public function test_get_avatar_applies_field_mappings_and_config_options(): void
    {
        $model = new BranchAvatarUser([
            'id' => 1,
            'contact_email' => 'mapped@example.com',
        ]);

        $resolution = $this->service()->getAvatar($model, [
            'field_mappings' => ['email' => ['contact_email']],
            'config' => ['prefer_model_avatar' => false, 'prefer_gravatar' => true],
        ]);

        $this->assertInstanceOf(AvatarResolution::class, $resolution);
        $this->assertTrue($resolution->isGravatar());
        $this->assertStringContainsString(md5('mapped@example.com'), $resolution->getUrl());
    }

    public function test_get_avatar_cached_builds_a_key_for_a_model_source(): void
    {
        $model = new BranchAvatarUser([
            'id' => 7,
            'name' => 'Cached Model',
        ]);

        $resolution = $this->service()->getAvatarCached($model, [], 60);

        $this->assertInstanceOf(AvatarResolution::class, $resolution);
        $this->assertTrue($resolution->isFromModel());
    }

    public function test_get_avatar_cached_builds_a_key_for_a_callable_source(): void
    {
        $resolution = $this->service()->getAvatarCached(
            static fn (): string => 'https://example.com/custom.png',
            [],
            60,
        );

        $this->assertInstanceOf(AvatarResolution::class, $resolution);
        $this->assertSame('https://example.com/custom.png', $resolution->getUrl());
    }
}

/**
 * Treats the configured font values as missing on disk so the font-resolution
 * fallbacks can be exercised; all other behaviour is inherited unchanged.
 */
class FontMissingAvatarService extends AvatarService
{
    /** @var list<string> */
    public array $missing = [];

    #[\Override]
    protected function getFontPaths(string $fontName): array
    {
        if (in_array($fontName, $this->missing, true)) {
            return ['/no/such/location/' . $fontName];
        }

        return parent::getFontPaths($fontName);
    }
}

/**
 * Reports image processing as unavailable so the generation/save Throwable
 * branches and the default-avatar fallback are taken.
 */
class NoImageAvatarService extends AvatarService
{
    #[\Override]
    public function isImageProcessingAvailable(): bool
    {
        return false;
    }
}

/**
 * Forces both the avatar render and the default-avatar canvas to fail, so the
 * final SVG fallback is reached.
 */
class BrokenImageAvatarService extends AvatarService
{
    #[\Override]
    public function isImageProcessingAvailable(): bool
    {
        return false;
    }

    #[\Override]
    protected function newImage(): ImageInterface
    {
        throw new RuntimeException('no driver available');
    }
}

/**
 * Minimal in-memory Eloquent model for model-source resolution.
 */
class BranchAvatarUser extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public $exists = true;
}
