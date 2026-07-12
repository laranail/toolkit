<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;

/**
 * Resolves an avatar from a variety of sources (string, Eloquent model or
 * callback) using a configurable fallback strategy.
 */
class AvatarResolver
{
    /**
     * Default field mappings for common avatar storage patterns.
     *
     * @var array<string, list<string>>
     */
    protected array $defaultFieldMappings = [
        'email' => ['email', 'email_address', 'user_email'],
        'name' => ['name', 'full_name', 'display_name', 'username'],
        'avatar_url' => ['avatar', 'avatar_url', 'profile_picture', 'photo_url'],
        'first_name' => ['first_name', 'firstname', 'given_name'],
        'last_name' => ['last_name', 'lastname', 'family_name'],
    ];

    /**
     * Configuration options.
     *
     * @var array<string, mixed>
     */
    protected array $config = [
        'prefer_gravatar' => true,
        'prefer_model_avatar' => true,
        'fallback_to_initials' => true,
        'default_size' => 200,
        'default_https' => true,
        'cache_avatars' => true,
        'cache_ttl' => 3600,
        'fallback_name' => 'User',
        'fallback_shape' => 'circle',
        'fallback_background_color' => null,
        'fallback_foreground_color' => null,
    ];

    public function __construct(
        protected AvatarServiceInterface $avatarService,
        protected GravatarServiceInterface $gravatarService,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function resolve(string|Model|Closure $source, array $options = []): AvatarResolution
    {
        $config = array_merge($this->config, $options);

        if ($source instanceof Closure) {
            return $this->resolveFromCallback($source, $config);
        }

        if ($source instanceof Model) {
            return $this->resolveFromModel($source, $config);
        }

        return $this->resolveFromString($source, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolveFromCallback(Closure $callback, array $config): AvatarResolution
    {
        $context = new AvatarResolutionContextData($this->avatarService, $this->gravatarService, $config);

        $result = $callback($context);

        if ($result instanceof AvatarResolution) {
            return $result;
        }

        if (is_string($result)) {
            return new AvatarResolution($result, 'callback', 'url');
        }

        throw new InvalidArgumentException('Callback must return AvatarResolution or string URL.');
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolveFromModel(Model $model, array $config): AvatarResolution
    {
        if ($config['prefer_model_avatar']) {
            $avatarUrl = $this->getModelAvatarUrl($model);
            if ($avatarUrl !== null) {
                return new AvatarResolution($avatarUrl, 'model', 'url');
            }
        }

        if ($config['prefer_gravatar']) {
            $email = $this->getModelEmail($model);
            if ($email !== null) {
                $gravatarUrl = $this->gravatarService
                    ->setEmail($email)
                    ->setSize($this->sizeOf($config))
                    ->setHttps($this->httpsOf($config))
                    ->generate();

                return new AvatarResolution($gravatarUrl, 'model', 'gravatar');
            }
        }

        if ($config['fallback_to_initials']) {
            $name = $this->getModelName($model);
            if ($name !== null) {
                $initialsAvatar = $this->avatarService
                    ->setName($name)
                    ->setSize($this->sizeOf($config), $this->sizeOf($config))
                    ->generate();

                return new AvatarResolution($initialsAvatar, 'model', 'initials');
            }
        }

        return new AvatarResolution(
            $this->getFallbackInitialsAvatar($this->sizeOf($config), $config),
            'model',
            'fallback',
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolveFromString(string $source, array $config): AvatarResolution
    {
        if (filter_var($source, FILTER_VALIDATE_EMAIL)) {
            return $this->resolveFromEmail($source, $config);
        }

        return $this->resolveFromName($source, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolveFromEmail(string $email, array $config): AvatarResolution
    {
        if ($config['prefer_gravatar']) {
            $gravatarUrl = $this->gravatarService
                ->setEmail($email)
                ->setSize($this->sizeOf($config))
                ->setHttps($this->httpsOf($config))
                ->generate();

            return new AvatarResolution($gravatarUrl, 'email', 'gravatar');
        }

        if ($config['fallback_to_initials']) {
            $initialsAvatar = $this->avatarService
                ->setName($email)
                ->setSize($this->sizeOf($config), $this->sizeOf($config))
                ->generate();

            return new AvatarResolution($initialsAvatar, 'email', 'initials');
        }

        return new AvatarResolution(
            $this->getFallbackInitialsAvatar($this->sizeOf($config), $config),
            'email',
            'fallback',
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolveFromName(string $name, array $config): AvatarResolution
    {
        $initialsAvatar = $this->avatarService
            ->setName($name)
            ->setSize($this->sizeOf($config), $this->sizeOf($config))
            ->generate();

        return new AvatarResolution($initialsAvatar, 'name', 'initials');
    }

    protected function getModelAvatarUrl(Model $model): ?string
    {
        foreach ($this->defaultFieldMappings['avatar_url'] as $field) {
            $value = $model->getAttribute($field);
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }
        }

        return null;
    }

    protected function getModelEmail(Model $model): ?string
    {
        foreach ($this->defaultFieldMappings['email'] as $field) {
            $value = $model->getAttribute($field);
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }

    protected function getModelName(Model $model): ?string
    {
        foreach ($this->defaultFieldMappings['name'] as $field) {
            $value = $model->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $firstName = $this->firstAttribute($model, $this->defaultFieldMappings['first_name']);
        $lastName = $this->firstAttribute($model, $this->defaultFieldMappings['last_name']);

        if ($firstName !== null && $lastName !== null) {
            return trim($firstName . ' ' . $lastName);
        }

        return $firstName ?? $lastName;
    }

    /**
     * @param list<string> $fields
     */
    private function firstAttribute(Model $model, array $fields): ?string
    {
        foreach ($fields as $field) {
            $value = $model->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function getFallbackInitialsAvatar(int $size, array $config = []): string
    {
        $fallbackName = is_string($config['fallback_name'] ?? null) ? $config['fallback_name'] : 'User';
        $fallbackShape = is_string($config['fallback_shape'] ?? null) ? $config['fallback_shape'] : 'circle';
        $fallbackBackground = $config['fallback_background_color'] ?? null;
        $fallbackForeground = $config['fallback_foreground_color'] ?? null;

        $avatar = $this->avatarService
            ->setName($fallbackName)
            ->setSize($size, $size)
            ->setShape($fallbackShape);

        if (is_string($fallbackBackground)) {
            $avatar = $avatar->setBackgroundColor($fallbackBackground);
        }

        if (is_string($fallbackForeground)) {
            $avatar = $avatar->setForegroundColor($fallbackForeground);
        }

        return $avatar->generate();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function sizeOf(array $config): int
    {
        $size = $config['default_size'] ?? null;

        return is_numeric($size) ? (int) $size : 200;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function httpsOf(array $config): bool
    {
        return (bool) ($config['default_https'] ?? true);
    }

    /**
     * @param array<string, list<string>> $mappings
     */
    public function setFieldMappings(array $mappings): self
    {
        $this->defaultFieldMappings = array_merge($this->defaultFieldMappings, $mappings);

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getFieldMappings(): array
    {
        return $this->defaultFieldMappings;
    }
}
