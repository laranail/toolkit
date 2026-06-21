<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar;

use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;

/**
 * Context object passed to avatar resolution callbacks.
 *
 * Provides access to the avatar and gravatar services plus helper methods for
 * generating avatars and building {@see AvatarResolution} results.
 */
readonly class AvatarResolutionContextData
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public AvatarServiceInterface $avatarService,
        public GravatarServiceInterface $gravatarService,
        public array $config = [],
    ) {}

    public function avatar(): AvatarServiceInterface
    {
        return $this->avatarService;
    }

    public function gravatar(): GravatarServiceInterface
    {
        return $this->gravatarService;
    }

    public function generateGravatar(string $email, int $size = 200, bool $https = true): string
    {
        return $this->gravatarService
            ->setEmail($email)
            ->setSize($size)
            ->setHttps($https)
            ->generate();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function generateCustom(string $name, array $options = []): string
    {
        $avatar = $this->avatarService->setName($name);

        if (isset($options['size'])) {
            $size = is_array($options['size']) ? $options['size'] : [$options['size'], $options['size']];
            $avatar = $avatar->setSize((int) $size[0], (int) $size[1]);
        }

        if (isset($options['shape'])) {
            $avatar = $avatar->setShape((string) $options['shape']);
        }

        if (isset($options['background'])) {
            $avatar = $avatar->setBackgroundColor((string) $options['background']);
        }

        if (isset($options['foreground'])) {
            $avatar = $avatar->setForegroundColor((string) $options['foreground']);
        }

        if (isset($options['font'])) {
            $avatar = $avatar->useFontByName((string) $options['font']);
        }

        return $avatar->generate();
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createResult(string $url, string $sourceType = 'callback', string $method = 'custom', array $metadata = []): AvatarResolution
    {
        return new AvatarResolution($url, $sourceType, $method, $metadata);
    }

    public function createGravatarResult(string $email, int $size = 200, bool $https = true): AvatarResolution
    {
        $url = $this->generateGravatar($email, $size, $https);

        return $this->createResult($url, 'callback', 'gravatar', ['email' => $email, 'size' => $size]);
    }

    public function createInitialsResult(string $name, int $size = 200): AvatarResolution
    {
        $url = $this->generateCustom($name, ['size' => $size]);

        return $this->createResult($url, 'callback', 'initials', ['name' => $name, 'size' => $size]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createCustomResult(string $name, array $options = []): AvatarResolution
    {
        $url = $this->generateCustom($name, $options);

        return $this->createResult($url, 'callback', 'custom', array_merge(['name' => $name], $options));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createUrlResult(string $url, array $metadata = []): AvatarResolution
    {
        return $this->createResult($url, 'callback', 'url', $metadata);
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function hasConfig(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'config' => $this->config,
            'services' => [
                'avatar_service' => $this->avatarService::class,
                'gravatar_service' => $this->gravatarService::class,
            ],
        ];
    }
}
