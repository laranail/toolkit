<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha\Results;

/**
 * Immutable value object describing the outcome of a CAPTCHA verification.
 *
 * Constructed only through the {@see self::success()} and {@see self::failure()}
 * named constructors so callers cannot accidentally build an inconsistent state
 * (e.g. a "successful" result that also carries error codes).
 */
final readonly class CaptchaVerificationResult
{
    /**
     * @param array<int, string>   $errors
     * @param array<string, mixed> $context
     */
    private function __construct(
        public bool $success,
        public string $providerName,
        public float $score = 0.0,
        public array $errors = [],
        public array $context = [],
    ) {}

    /**
     * Build a successful verification result.
     *
     * @param array<string, mixed> $context
     */
    public static function success(string $providerName, float $score = 0.0, array $context = []): self
    {
        return new self(
            success: true,
            providerName: $providerName,
            score: $score,
            errors: [],
            context: $context,
        );
    }

    /**
     * Build a failed verification result.
     *
     * @param array<int, string>   $errors
     * @param array<string, mixed> $context
     */
    public static function failure(string $providerName, array $errors = [], array $context = []): self
    {
        return new self(
            success: false,
            providerName: $providerName,
            score: 0.0,
            errors: $errors,
            context: $context,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * @return array<int, string>
     */
    public function errorCodes(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    public function score(): float
    {
        return $this->score;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function providerName(): string
    {
        return $this->providerName;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array{success: bool, provider: string, score: float, errors: array<int, string>, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider' => $this->providerName,
            'score' => $this->score,
            'errors' => $this->errors,
            'context' => $this->context,
        ];
    }
}
