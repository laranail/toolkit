<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Modules\Avatar;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolver;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class AvatarResolverDirectTest extends TestCase
{
    private function resolver(): AvatarResolver
    {
        return new AvatarResolver(
            $this->app->make(AvatarServiceInterface::class),
            $this->app->make(GravatarServiceInterface::class),
        );
    }

    public function test_callback_returning_invalid_value_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolver()->resolve(fn (): int => 42);
    }

    public function test_email_string_with_gravatar_disabled_uses_initials(): void
    {
        $resolution = $this->resolver()->resolve('user@example.com', ['prefer_gravatar' => false]);

        $this->assertTrue($resolution->isFromEmail());
        $this->assertTrue($resolution->isInitials());
    }

    public function test_email_string_with_all_strategies_disabled_falls_back(): void
    {
        $resolution = $this->resolver()->resolve('user@example.com', [
            'prefer_gravatar' => false,
            'fallback_to_initials' => false,
        ]);

        $this->assertTrue($resolution->isFromEmail());
        $this->assertTrue($resolution->isFallback());
    }

    public function test_model_with_no_usable_fields_falls_back(): void
    {
        $model = new ResolverDirectUser([]);

        $resolution = $this->resolver()->resolve($model, [
            'prefer_model_avatar' => false,
            'prefer_gravatar' => false,
            'fallback_to_initials' => false,
        ]);

        $this->assertTrue($resolution->isFromModel());
        $this->assertTrue($resolution->isFallback());
    }

    public function test_model_name_built_from_first_and_last_name(): void
    {
        $model = new ResolverDirectUser(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $resolution = $this->resolver()->resolve($model, [
            'prefer_model_avatar' => false,
            'prefer_gravatar' => false,
        ]);

        $this->assertTrue($resolution->isInitials());
    }

    public function test_fallback_honours_custom_colors_and_shape(): void
    {
        $resolution = $this->resolver()->resolve('user@example.com', [
            'prefer_gravatar' => false,
            'fallback_to_initials' => false,
            'fallback_shape' => 'square',
            'fallback_background_color' => '#123456',
            'fallback_foreground_color' => '#abcdef',
        ]);

        $this->assertTrue($resolution->isFallback());
        $this->assertStringStartsWith('data:image/png;base64,', $resolution->getUrl());
    }

    public function test_config_and_field_mapping_mutators(): void
    {
        $resolver = $this->resolver();

        $same = $resolver->setConfig(['default_size' => 512]);
        $this->assertSame($resolver, $same);
        $this->assertSame(512, $resolver->getConfig()['default_size']);

        $resolver->setFieldMappings(['email' => ['contact_email']]);
        $this->assertContains('contact_email', $resolver->getFieldMappings()['email']);
    }
}

/**
 * Minimal model exercising AvatarResolver's field-mapping lookups.
 */
class ResolverDirectUser extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public $exists = true;
}
