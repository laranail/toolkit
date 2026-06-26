<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Avatar;

use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarGeneration;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolution;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class AvatarDtosTest extends TestCase
{
    public function test_avatar_generation_exposes_every_accessor(): void
    {
        $generation = new AvatarGeneration(
            url: 'https://example.com/a.png',
            format: 'png',
            width: 200,
            height: 200,
            shape: 'circle',
            colors: ['background' => '#000', 'foreground' => '#fff'],
            metadata: ['initials' => 'AB'],
        );

        $this->assertSame('https://example.com/a.png', $generation->getUrl());
        $this->assertSame('png', $generation->getFormat());
        $this->assertSame(200, $generation->getWidth());
        $this->assertSame(200, $generation->getHeight());
        $this->assertSame('circle', $generation->getShape());
        $this->assertSame(['background' => '#000', 'foreground' => '#fff'], $generation->getColors());
        $this->assertSame(['initials' => 'AB'], $generation->getMetadata());
        $this->assertTrue($generation->isSquare());
        $this->assertTrue($generation->isCircular());
        $this->assertSame(1.0, $generation->getAspectRatio());
        $this->assertSame('#000', $generation->getBackgroundColor());
        $this->assertSame('#fff', $generation->getForegroundColor());
        $this->assertSame('https://example.com/a.png', (string) $generation);

        $array = $generation->toArray();
        $this->assertTrue($array['is_square']);
        $this->assertTrue($array['is_circular']);
        $this->assertSame(1.0, $array['aspect_ratio']);
    }

    public function test_avatar_generation_non_square_and_missing_colors(): void
    {
        $generation = new AvatarGeneration(
            url: 'u',
            format: 'svg',
            width: 300,
            height: 150,
            shape: 'square',
            colors: [],
        );

        $this->assertFalse($generation->isSquare());
        $this->assertFalse($generation->isCircular());
        $this->assertSame(2.0, $generation->getAspectRatio());
        $this->assertNull($generation->getBackgroundColor());
        $this->assertNull($generation->getForegroundColor());
        $this->assertSame([], $generation->getMetadata());
    }

    public function test_avatar_resolution_source_and_method_flags(): void
    {
        $resolution = new AvatarResolution('url', 'model', 'gravatar', ['k' => 'v']);

        $this->assertSame('url', $resolution->getUrl());
        $this->assertSame('model', $resolution->getSourceType());
        $this->assertSame('gravatar', $resolution->getMethod());
        $this->assertSame(['k' => 'v'], $resolution->getMetadata());

        $this->assertTrue($resolution->isGravatar());
        $this->assertFalse($resolution->isInitials());
        $this->assertFalse($resolution->isUrl());
        $this->assertFalse($resolution->isFallback());

        $this->assertTrue($resolution->isFromModel());
        $this->assertFalse($resolution->isFromEmail());
        $this->assertFalse($resolution->isFromName());
        $this->assertFalse($resolution->isFromCallback());

        $this->assertSame('Gravatar for model email', $resolution->getDescription());
        $this->assertSame('url', (string) $resolution);

        $array = $resolution->toArray();
        $this->assertTrue($array['is_gravatar']);
        $this->assertSame('Gravatar for model email', $array['description']);
    }

    public function test_avatar_resolution_describes_each_source_combination(): void
    {
        $this->assertSame('Stored avatar from model', (new AvatarResolution('u', 'model', 'url'))->getDescription());
        $this->assertSame('Initials avatar for model name', (new AvatarResolution('u', 'model', 'initials'))->getDescription());
        $this->assertSame('Fallback initials avatar for model', (new AvatarResolution('u', 'model', 'fallback'))->getDescription());
        $this->assertSame('Avatar from model', (new AvatarResolution('u', 'model', 'other'))->getDescription());

        $this->assertSame('Gravatar for email', (new AvatarResolution('u', 'email', 'gravatar'))->getDescription());
        $this->assertSame('Initials avatar for email', (new AvatarResolution('u', 'email', 'initials'))->getDescription());
        $this->assertSame('Fallback initials avatar for email', (new AvatarResolution('u', 'email', 'fallback'))->getDescription());
        $this->assertSame('Avatar for email', (new AvatarResolution('u', 'email', 'other'))->getDescription());

        $this->assertSame('Initials avatar for name', (new AvatarResolution('u', 'name', 'initials'))->getDescription());
        $this->assertSame('Custom avatar from callback', (new AvatarResolution('u', 'callback', 'custom'))->getDescription());
        $this->assertSame('Avatar', (new AvatarResolution('u', 'unknown', 'x'))->getDescription());
    }

    public function test_avatar_resolution_flag_variants(): void
    {
        $this->assertTrue((new AvatarResolution('u', 'email', 'initials'))->isInitials());
        $this->assertTrue((new AvatarResolution('u', 'callback', 'url'))->isUrl());
        $this->assertTrue((new AvatarResolution('u', 'model', 'fallback'))->isFallback());
        $this->assertTrue((new AvatarResolution('u', 'email', 'gravatar'))->isFromEmail());
        $this->assertTrue((new AvatarResolution('u', 'name', 'initials'))->isFromName());
        $this->assertTrue((new AvatarResolution('u', 'callback', 'custom'))->isFromCallback());
    }
}
