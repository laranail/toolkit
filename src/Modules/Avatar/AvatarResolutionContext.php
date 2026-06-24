<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar;

use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;

/**
 * A context object exposing avatar and gravatar helpers to resolution
 * callbacks. Returned results are {@see AvatarResolution} instances.
 */
class AvatarResolutionContext
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly AvatarServiceInterface $avatar,
        public readonly GravatarServiceInterface $gravatar,
        public readonly array $config,
    ) {}

    public function gravatar(string $email, int $size = 200, bool $https = true): string
    {
        return $this->gravatar
            ->setEmail($email)
            ->setSize($size)
            ->setHttps($https)
            ->generate();
    }

    public function initials(string $name, int $size = 200): string
    {
        return $this->avatar
            ->setName($name)
            ->setSize($size, $size)
            ->generate();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function custom(string $name, array $options = []): string
    {
        $avatar = $this->avatar->setName($name);

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
    public function result(string $url, string $sourceType = 'callback', string $method = 'custom', array $metadata = []): AvatarResolution
    {
        return new AvatarResolution($url, $sourceType, $method, $metadata);
    }

    public function gravatarResult(string $email, int $size = 200, bool $https = true): AvatarResolution
    {
        $url = $this->gravatar($email, $size, $https);

        return $this->result($url, 'callback', 'gravatar', ['email' => $email, 'size' => $size]);
    }

    public function initialsResult(string $name, int $size = 200): AvatarResolution
    {
        $url = $this->initials($name, $size);

        return $this->result($url, 'callback', 'initials', ['name' => $name, 'size' => $size]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function customResult(string $name, array $options = []): AvatarResolution
    {
        $url = $this->custom($name, $options);

        return $this->result($url, 'callback', 'custom', array_merge(['name' => $name], $options));
    }

    public function config(string $key, mixed $default = null): mixed
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
    public function getConfig(): array
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
}
