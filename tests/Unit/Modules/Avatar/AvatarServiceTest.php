<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Avatar;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Contracts\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Enums\AvatarFont;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Services\AvatarService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class AvatarServiceTest extends TestCase
{
    private function service(): AvatarService
    {
        $service = $this->app->make(AvatarServiceInterface::class);
        $this->assertInstanceOf(AvatarService::class, $service);

        return $service;
    }

    /**
     * Decode a `data:image/png;base64,...` URI into the raw PNG bytes.
     */
    private function decodePng(string $dataUri): string
    {
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        return (string) base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
    }

    public function test_it_resolves_from_the_container_via_the_contract(): void
    {
        $this->assertInstanceOf(AvatarService::class, $this->app->make(AvatarServiceInterface::class));
    }

    public function test_initials_for_a_single_word_uses_first_char(): void
    {
        $this->assertSame('J', $this->service()->setName('Jane')->makeInitials());
    }

    public function test_initials_for_a_single_word_respects_char_count(): void
    {
        $this->assertSame('Ja', $this->service()->setName('Jane')->setChars(2)->makeInitials());
    }

    public function test_initials_for_two_words_uses_first_char_of_each(): void
    {
        $this->assertSame('JD', $this->service()->setName('Jane Doe')->setChars(2)->makeInitials());
    }

    public function test_initials_uppercase_flag(): void
    {
        $this->assertSame('JD', $this->service()->setName('jane doe')->setChars(2)->setUppercase(true)->makeInitials());
        $this->assertSame('jd', $this->service()->setName('jane doe')->setChars(2)->setUppercase(false)->makeInitials());
    }

    public function test_initials_ascii_flag_transliterates(): void
    {
        $initials = $this->service()->setName('Ñoño')->setAscii(true)->setUppercase(true)->makeInitials();

        $this->assertSame('N', $initials);
    }

    public function test_color_hashing_is_deterministic_for_a_given_name(): void
    {
        $a = $this->service()->setName('Jane Doe');
        $b = $this->service()->setName('Jane Doe');

        $this->assertSame($a->getRandomBackgroundColor(), $b->getRandomBackgroundColor());
        $this->assertSame($a->getRandomForegroundColor(), $b->getRandomForegroundColor());
    }

    public function test_color_hashing_handles_multibyte_names(): void
    {
        // Regression: the legacy byte-indexing under a multibyte length bound
        // produced wrong/empty hashes for UTF-8 names.
        $color = $this->service()->setName('日本語の名前')->getRandomBackgroundColor();

        $this->assertContains($color, $this->service()->getAvailableBackgroundColors());
    }

    public function test_generate_produces_a_png_data_uri_matching_config(): void
    {
        $uri = $this->service()
            ->setName('Jane Doe')
            ->setSize(128, 128)
            ->setCacheEnabled(false)
            ->generate();

        $png = $this->decodePng($uri);
        $this->assertNotSame('', $png);

        $info = getimagesizefromstring($png);
        $this->assertIsArray($info);
        $this->assertSame('image/png', $info['mime']);
        $this->assertSame(128, $info[0]);
        $this->assertSame(128, $info[1]);
    }

    public function test_generate_renders_text_under_gd(): void
    {
        // Regression for the blank-avatar bug: the rendered avatar must differ
        // from a background-only image of the same size/colors. We force the GD
        // driver path by asserting the text layer changes the output bytes.
        $withText = $this->service()
            ->setName('Jane Doe')
            ->setColors('#3F51B5', '#FFFFFF')
            ->setSize(150, 150)
            ->setCacheEnabled(false)
            ->generate();

        $blank = $this->service()
            ->setName('Jane Doe')
            ->setColors('#3F51B5', '#FFFFFF')
            ->setSize(150, 150)
            ->setCacheEnabled(false)
            // Make the foreground identical to the background so the glyphs are
            // invisible — this yields a background-only image to compare with.
            ->setForegroundColor('#3F51B5')
            ->generate();

        $this->assertNotSame(
            $this->decodePng($blank),
            $this->decodePng($withText),
            'Rendered avatar should differ from a background-only image (text must render).',
        );
    }

    public function test_generate_base64_and_data_uri_share_the_png_shape(): void
    {
        $service = $this->service()->setName('A B')->setSize(64, 64)->setCacheEnabled(false);

        $this->assertStringStartsWith('data:image/png;base64,', $service->generateBase64());
        $this->assertStringStartsWith('data:image/png;base64,', $service->generateDataUri());
    }

    public function test_circle_shape_stays_within_canvas_dimensions(): void
    {
        $uri = $this->service()
            ->setName('Jane Doe')
            ->setShape('circle')
            ->setBorderSize(4)
            ->setSize(100, 100)
            ->setCacheEnabled(false)
            ->generate();

        $info = getimagesizefromstring($this->decodePng($uri));
        $this->assertIsArray($info);
        $this->assertSame(100, $info[0]);
        $this->assertSame(100, $info[1]);
    }

    public function test_square_shape_generates_successfully(): void
    {
        $uri = $this->service()
            ->setName('Jane Doe')
            ->setShape('square')
            ->setSize(90, 90)
            ->setCacheEnabled(false)
            ->generate();

        $info = getimagesizefromstring($this->decodePng($uri));
        $this->assertIsArray($info);
        $this->assertSame(90, $info[0]);
    }

    #[Group('security')]
    public function test_invalid_hex_background_color_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->setBackgroundColor('red; DROP TABLE users');
    }

    #[Group('security')]
    public function test_invalid_hex_foreground_color_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->setForegroundColor('not-a-color');
    }

    #[Group('security')]
    public function test_set_colors_rejects_invalid_hex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->setColors('#FFFFFF', 'zzz');
    }

    #[Group('security')]
    public function test_invalid_border_hex_color_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->setBorderColor('#GGGGGG');
    }

    public function test_valid_hex_colors_are_accepted(): void
    {
        $service = $this->service()->setColors('#FFF', '#FF5722');

        $this->assertSame('#FFF', $service->getBackgroundColor());
        $this->assertSame('#FF5722', $service->getForegroundColor());
    }

    public function test_border_color_keywords_are_accepted(): void
    {
        $this->assertSame('foreground', $this->service()->setBorderColor('foreground')->getBorderColor());
        $this->assertSame('background', $this->service()->setBorderColor('background')->getBorderColor());
    }

    public function test_set_shape_rejects_unknown_shapes_and_accepts_known_ones(): void
    {
        // Regression for the Arr::has() value-membership bug: valid shapes must
        // be accepted, invalid ones rejected.
        $this->assertSame('square', $this->service()->setShape('square')->getShape());

        $this->expectException(InvalidArgumentException::class);
        $this->service()->setShape('triangle');
    }

    public function test_set_font_by_name_accepts_bundled_fonts_and_rejects_others(): void
    {
        // Regression for the Arr::has() value-membership bug on the font list.
        $service = $this->service()->setFontByName('FreeSerif.ttf');

        $this->assertStringEndsWith('FreeSerif.ttf', $service->getFontPath());

        $this->expectException(InvalidArgumentException::class);
        $this->service()->setFontByName('Comic-Sans.ttf');
    }

    public function test_default_font_helpers(): void
    {
        $service = $this->service();

        $this->assertSame(AvatarFont::ROBOTO_BOLD, $service->getDefaultFont());
        $this->assertSame('Roboto-Bold.ttf', $service->getDefaultFontName());

        $service->setDefaultFont(AvatarFont::FREE_SERIF);
        $this->assertSame('FreeSerif.ttf', $service->getDefaultFontName());
    }

    public function test_available_fonts_resolve_to_bundled_paths(): void
    {
        $fonts = $this->service()->getAvailableFonts();

        $this->assertArrayHasKey('Roboto-Bold.ttf', $fonts);
        $this->assertFileExists($fonts['Roboto-Bold.ttf']);
    }

    public function test_get_gravatar_for_email_applies_size_rating_and_default(): void
    {
        $url = $this->service()->getGravatarForEmail('user@example.com', 120, true, 'pg', 'retro');

        $this->assertStringContainsString('s=120', $url);
        $this->assertStringContainsString('r=pg', $url);
        $this->assertStringContainsString('d=retro', $url);
        $this->assertStringStartsWith('https://secure.gravatar.com/avatar/', $url);
    }

    public function test_get_gravatar_uses_email_extracted_from_name(): void
    {
        // Regression: the legacy getGravatar() ignored $rating and $default.
        $url = $this->service()->setName('user@example.com')->getGravatar(64, true, 'pg', 'identicon');

        $this->assertNotNull($url);
        $this->assertStringContainsString('r=pg', $url);
        $this->assertStringContainsString('d=identicon', $url);
    }

    public function test_get_gravatar_returns_null_without_an_email(): void
    {
        $this->assertNull($this->service()->setName('Jane Doe')->getGravatar());
    }

    public function test_set_quality_validates_bounds(): void
    {
        $this->assertSame(75, $this->service()->setQuality(75)->getQuality());

        $this->expectException(InvalidArgumentException::class);
        $this->service()->setQuality(0);
    }

    public function test_save_writes_a_file_and_cleans_up(): void
    {
        $path = sys_get_temp_dir() . '/laranail_avatar_' . uniqid() . '.png';

        try {
            $ok = $this->service()->setName('Jane Doe')->setSize(48, 48)->save($path);

            $this->assertTrue($ok);
            $this->assertFileExists($path);

            $info = getimagesizefromstring((string) file_get_contents($path));
            $this->assertIsArray($info);
            $this->assertSame(48, $info[0]);
        } finally {
            @unlink($path);
        }
    }

    public function test_generate_is_cached_when_enabled(): void
    {
        $service = $this->service()->setName('Cached User')->setSize(40, 40)->setCacheEnabled(true);

        $first = $service->generate();
        $second = $service->generate();

        $this->assertSame($first, $second);
    }
}
