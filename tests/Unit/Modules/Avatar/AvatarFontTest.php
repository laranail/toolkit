<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Avatar;

use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarFont;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class AvatarFontTest extends TestCase
{
    public function test_default_is_roboto_bold(): void
    {
        $this->assertSame(AvatarFont::ROBOTO_BOLD, AvatarFont::default());
        $this->assertSame('Roboto-Bold.ttf', AvatarFont::default()->value);
    }

    public function test_from_value_or_null_resolves_known_and_unknown_values(): void
    {
        $this->assertSame(AvatarFont::FREE_SERIF, AvatarFont::fromValueOrNull('FreeSerif.ttf'));
        $this->assertNull(AvatarFont::fromValueOrNull('does-not-exist.ttf'));
        $this->assertNull(AvatarFont::fromValueOrNull(null));
    }

    public function test_names_and_values_expose_all_cases(): void
    {
        $this->assertSame(['ROBOTO_BOLD', 'FREE_SERIF', 'MSYH'], AvatarFont::names());
        $this->assertSame(['Roboto-Bold.ttf', 'FreeSerif.ttf', 'msyh.ttf'], AvatarFont::values());
    }

    public function test_display_metadata_is_resolved_natively(): void
    {
        $this->assertSame('Roboto Bold', AvatarFont::ROBOTO_BOLD->getDisplayName());
        $this->assertSame('Microsoft YaHei', AvatarFont::MSYH->getDisplayName());
        $this->assertSame('sans-serif', AvatarFont::ROBOTO_BOLD->getCategory());
        $this->assertSame('serif', AvatarFont::FREE_SERIF->getCategory());
        $this->assertNotSame('', AvatarFont::FREE_SERIF->getDescription());
        $this->assertTrue(AvatarFont::MSYH->supportsUnicode());
    }

    public function test_get_by_category_filters_cases(): void
    {
        $serif = AvatarFont::getByCategory('serif');

        $this->assertSame([AvatarFont::FREE_SERIF], $serif);
        $this->assertSame([], AvatarFont::getByCategory('unknown'));
    }
}
