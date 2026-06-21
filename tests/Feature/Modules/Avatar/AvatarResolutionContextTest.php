<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Modules\Avatar;

use Simtabi\Laranail\Toolkit\Modules\Avatar\Contracts\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\DataTransferObjects\AvatarResolution;
use Simtabi\Laranail\Toolkit\Modules\Avatar\DataTransferObjects\AvatarResolutionContextData;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Services\AvatarResolutionContext;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Contracts\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class AvatarResolutionContextTest extends TestCase
{
    private function context(array $config = ['theme' => 'dark']): AvatarResolutionContext
    {
        return new AvatarResolutionContext(
            $this->app->make(AvatarServiceInterface::class),
            $this->app->make(GravatarServiceInterface::class),
            $config,
        );
    }

    private function contextData(array $config = ['theme' => 'dark']): AvatarResolutionContextData
    {
        return new AvatarResolutionContextData(
            $this->app->make(AvatarServiceInterface::class),
            $this->app->make(GravatarServiceInterface::class),
            $config,
        );
    }

    public function test_context_generates_gravatar_initials_and_custom(): void
    {
        $context = $this->context();

        $this->assertStringContainsString('gravatar.com/avatar/', $context->gravatar('a@b.com'));
        $this->assertStringStartsWith('data:image/png;base64,', $context->initials('Jane Doe'));

        $custom = $context->custom('Jane Doe', [
            'size' => 100,
            'shape' => 'square',
            'background' => '#000000',
            'foreground' => '#ffffff',
        ]);
        $this->assertStringStartsWith('data:image/png;base64,', $custom);

        $customArraySize = $context->custom('Jane Doe', ['size' => [120, 120]]);
        $this->assertStringStartsWith('data:image/png;base64,', $customArraySize);
    }

    public function test_context_result_builders(): void
    {
        $context = $this->context();

        $result = $context->result('https://x/y.png');
        $this->assertInstanceOf(AvatarResolution::class, $result);
        $this->assertTrue($result->isFromCallback());

        $this->assertTrue($context->gravatarResult('a@b.com', 100)->isGravatar());
        $this->assertTrue($context->initialsResult('Jane', 64)->isInitials());
        $this->assertSame('custom', $context->customResult('Jane', ['size' => 50])->getMethod());
    }

    public function test_context_config_accessors(): void
    {
        $context = $this->context(['theme' => 'dark']);

        $this->assertSame('dark', $context->config('theme'));
        $this->assertSame('fallback', $context->config('missing', 'fallback'));
        $this->assertTrue($context->hasConfig('theme'));
        $this->assertFalse($context->hasConfig('nope'));
        $this->assertSame(['theme' => 'dark'], $context->getConfig());
    }

    public function test_context_data_services_and_builders(): void
    {
        $data = $this->contextData();

        $this->assertInstanceOf(AvatarServiceInterface::class, $data->avatar());
        $this->assertInstanceOf(GravatarServiceInterface::class, $data->gravatar());

        $this->assertStringContainsString('gravatar.com/avatar/', $data->generateGravatar('a@b.com'));
        $this->assertStringStartsWith('data:image/png;base64,', $data->generateCustom('Jane', [
            'size' => [64, 64],
            'shape' => 'circle',
            'background' => '#111111',
            'foreground' => '#eeeeee',
        ]));

        $this->assertTrue($data->createGravatarResult('a@b.com', 100)->isGravatar());
        $this->assertTrue($data->createInitialsResult('Jane', 64)->isInitials());
        $this->assertSame('custom', $data->createCustomResult('Jane', ['size' => 50])->getMethod());
        $this->assertTrue($data->createUrlResult('https://x/y.png')->isUrl());
        $this->assertTrue($data->createResult('u')->isFromCallback());
    }

    public function test_context_data_config_helpers_and_to_array(): void
    {
        $data = $this->contextData(['k' => 'v']);

        $this->assertSame('v', $data->getConfig('k'));
        $this->assertSame('d', $data->getConfig('absent', 'd'));
        $this->assertTrue($data->hasConfig('k'));
        $this->assertFalse($data->hasConfig('x'));
        $this->assertSame(['k' => 'v'], $data->getAllConfig());

        $array = $data->toArray();
        $this->assertSame(['k' => 'v'], $array['config']);
        $this->assertArrayHasKey('avatar_service', $array['services']);
        $this->assertArrayHasKey('gravatar_service', $array['services']);
    }
}
