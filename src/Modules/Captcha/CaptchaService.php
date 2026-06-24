<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha;

use InvalidArgumentException;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\HcaptchaProvider;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\RecaptchaProvider;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\TurnstileProvider;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;

/**
 * CAPTCHA service manager.
 *
 * Resolves verification drivers strictly from a fixed allow-list — provider
 * names coming from config or user input can never instantiate an arbitrary
 * class. Each driver is built lazily from the package config and cached.
 */
class CaptchaService
{
    /**
     * The only provider names that may ever be resolved.
     *
     * @var array<int, string>
     */
    private const ALLOWED_PROVIDERS = [
        'recaptcha',
        'turnstile',
        'hcaptcha',
    ];

    /**
     * Lazily resolved provider instances, keyed by name.
     *
     * @var array<string, CaptchaProviderInterface>
     */
    private array $providers = [];

    public function __construct(
        private string $defaultProvider = 'recaptcha',
    ) {}

    /**
     * Explicitly register (or override) a provider instance under a name.
     *
     * The name still has to belong to the allow-list to keep resolution safe.
     */
    public function registerProvider(CaptchaProviderInterface $provider, ?string $name = null): self
    {
        $name ??= $provider->getName();

        $this->assertAllowed($name);

        $this->providers[$name] = $provider;

        return $this;
    }

    /**
     * Resolve a provider by name (or the default when null).
     *
     * @throws InvalidArgumentException When the name is not in the allow-list.
     */
    public function getProvider(?string $name = null): CaptchaProviderInterface
    {
        $name ??= $this->defaultProvider;

        $this->assertAllowed($name);

        return $this->providers[$name] ??= $this->makeProvider($name);
    }

    /**
     * Verify a CAPTCHA token using the chosen (or default) provider.
     *
     * @param array<string, mixed> $options
     */
    public function verify(
        string $token,
        array $options = [],
        ?string $provider = null,
        ?string $remoteIp = null,
    ): CaptchaVerificationResult {
        $instance = $this->getProvider($provider);

        if (!$instance->isConfigured()) {
            return CaptchaVerificationResult::failure(
                $instance->getName(),
                ['Provider not properly configured'],
            );
        }

        return $instance->verify($token, $options, $remoteIp);
    }

    /**
     * Verify a token against every configured provider.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, CaptchaVerificationResult>
     */
    public function verifyWithAllProviders(string $token, array $options = [], ?string $remoteIp = null): array
    {
        $results = [];

        foreach (self::ALLOWED_PROVIDERS as $name) {
            $results[$name] = $this->verify($token, $options, $name, $remoteIp);
        }

        return $results;
    }

    /**
     * Get the public site key for the chosen (or default) provider.
     */
    public function getSiteKey(?string $provider = null): string
    {
        return $this->getProvider($provider)->getSiteKey();
    }

    public function hasProvider(string $name): bool
    {
        return in_array($name, self::ALLOWED_PROVIDERS, true);
    }

    /**
     * Set the default provider name.
     *
     * @throws InvalidArgumentException When the name is not in the allow-list.
     */
    public function setDefaultProvider(string $name): self
    {
        $this->assertAllowed($name);

        $this->defaultProvider = $name;

        return $this;
    }

    public function getDefaultProvider(): string
    {
        return $this->defaultProvider;
    }

    /**
     * @return array<int, string>
     */
    public function getProviderNames(): array
    {
        return self::ALLOWED_PROVIDERS;
    }

    /**
     * Determine whether at least one allow-listed provider is configured.
     */
    public function hasConfiguredProvider(): bool
    {
        return array_any(self::ALLOWED_PROVIDERS, fn ($name) => $this->getProvider($name)->isConfigured());
    }

    /**
     * Guard that a provider name belongs to the allow-list.
     *
     * @throws InvalidArgumentException
     */
    private function assertAllowed(string $name): void
    {
        if (!in_array($name, self::ALLOWED_PROVIDERS, true)) {
            throw new InvalidArgumentException("Unknown CAPTCHA provider '{$name}'.");
        }
    }

    /**
     * Build a driver from the package config. Only ever called with an
     * allow-listed name (guaranteed by {@see assertAllowed()}).
     */
    private function makeProvider(string $name): CaptchaProviderInterface
    {
        $siteKey = ToolkitConfig::string("laranail.toolkit.captcha.{$name}.site_key");
        $secretKey = ToolkitConfig::string("laranail.toolkit.captcha.{$name}.secret_key");
        $timeout = ToolkitConfig::int("laranail.toolkit.captcha.{$name}.timeout", 30);

        return match ($name) {
            'recaptcha' => new RecaptchaProvider(
                siteKey: $siteKey,
                secretKey: $secretKey,
                minScore: ToolkitConfig::float('laranail.toolkit.captcha.recaptcha.min_score', 0.5),
                timeout: $timeout,
            ),
            'turnstile' => new TurnstileProvider(
                siteKey: $siteKey,
                secretKey: $secretKey,
                timeout: $timeout,
            ),
            'hcaptcha' => new HcaptchaProvider(
                siteKey: $siteKey,
                secretKey: $secretKey,
                timeout: $timeout,
            ),
            default => throw new InvalidArgumentException("Unknown CAPTCHA provider '{$name}'."),
        };
    }
}
