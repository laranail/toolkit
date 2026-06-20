<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Gravatar\Contracts;

use Simtabi\Laranail\Toolkit\Modules\Gravatar\DataTransferObjects\GravatarResolution;

/**
 * Fluent, immutable builder for Gravatar URLs.
 *
 * Every `set*` method returns a new instance, so a shared/container-resolved
 * builder can be reused safely without state leaking between calls.
 */
interface GravatarServiceInterface
{
    public function setEmail(string $email): self;

    public function setSize(int $size): self;

    public function setHttps(bool $https): self;

    public function setRating(string $rating): self;

    public function setDefaultImage(string $defaultImage): self;

    public function setForceDefault(bool $forceDefault): self;

    public function setCustomDefaultUrl(?string $customUrl): self;

    public function getEmail(): ?string;

    public function getSize(): int;

    public function isHttps(): bool;

    public function getRating(): string;

    public function getDefaultImage(): string;

    public function isForceDefault(): bool;

    public function getCustomDefaultUrl(): ?string;

    /** @return list<string> */
    public function availableRatings(): array;

    /** @return list<string> */
    public function availableDefaultImages(): array;

    public function isValidEmail(string $email): bool;

    public function hashEmail(string $email): string;

    /** Build the Gravatar URL for the configured email. */
    public function generate(): string;

    /** Build a structured result describing the resolved Gravatar. */
    public function resolve(): GravatarResolution;
}
