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
            $avatar = $avatar->setSize(self::toInt($size[0] ?? null), self::toInt($size[1] ?? null));
        }

        if (isset($options['shape'])) {
            $avatar = $avatar->setShape(self::toString($options['shape']));
        }

        if (isset($options['background'])) {
            $avatar = $avatar->setBackgroundColor(self::toString($options['background']));
        }

        if (isset($options['foreground'])) {
            $avatar = $avatar->setForegroundColor(self::toString($options['foreground']));
        }

        if (isset($options['font'])) {
            $avatar = $avatar->useFontByName(self::toString($options['font']));
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

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : '';
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
