<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar;

use Illuminate\Database\Eloquent\Model;
use Intervention\Image\Interfaces\ImageInterface;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;

/**
 * Fluent, chainable avatar generation service.
 *
 * Creates avatar images from names, text or e-mail addresses, with optional
 * Gravatar integration and a flexible source-resolution pipeline. Bind by
 * contract and resolve through the container — there are no static factories.
 */
interface AvatarServiceInterface
{
    // Fluent setters
    public function setName(?string $name): self;

    public function setWidth(int $width): self;

    public function setHeight(int $height): self;

    public function setSize(int $width, int $height): self;

    public function setShape(string $shape): self;

    public function setBackgroundColor(string $color): self;

    public function setForegroundColor(string $color): self;

    public function setColors(string $backgroundColor, string $foregroundColor): self;

    public function setChars(int $chars): self;

    public function setFontSize(int $size): self;

    public function setBorderSize(int $size): self;

    public function setBorderColor(string $color): self;

    public function setUppercase(bool $uppercase): self;

    public function setAscii(bool $ascii): self;

    public function setCacheEnabled(bool $enabled): self;

    public function setCacheTtl(int $ttl): self;

    public function setFontPath(string $path): self;

    public function setQuality(int $quality): self;

    // Getters
    public function getName(): ?string;

    public function getWidth(): int;

    public function getHeight(): int;

    public function getShape(): string;

    public function getBackgroundColor(): string;

    public function getForegroundColor(): string;

    public function getChars(): int;

    public function getFontSize(): int;

    public function getBorderSize(): int;

    public function getBorderColor(): string;

    public function isUppercase(): bool;

    public function isAscii(): bool;

    public function isCacheEnabled(): bool;

    public function getCacheTtl(): int;

    public function getFontPath(): string;

    public function getQuality(): int;

    // Generation methods
    public function generate(): string;

    public function generateBase64(): string;

    public function generateDataUri(): string;

    public function save(string $path): bool;

    public function getImageObject(): ImageInterface;

    public function makeInitials(): string;

    // Utility methods
    public function getRandomBackgroundColor(): string;

    public function getRandomForegroundColor(): string;

    public function isImageProcessingAvailable(): bool;

    /** @return list<string> */
    public function getAvailableShapes(): array;

    /** @return list<string> */
    public function getAvailableBackgroundColors(): array;

    /** @return list<string> */
    public function getAvailableForegroundColors(): array;

    /** @return array<string, string> */
    public function getAvailableFonts(): array;

    /** @return list<string> */
    public function getAvailableFontNames(): array;

    /** @return list<string> */
    public function getAvailableFontValues(): array;

    /** @return list<AvatarFont> */
    public function getAvailableFontEnums(): array;

    public function getDefaultFont(): AvatarFont;

    public function getDefaultFontName(): string;

    public function setDefaultFont(AvatarFont $font): self;

    public function setDefaultFontName(string $fontName): self;

    public function useFont(AvatarFont $font): self;

    public function useFontByName(string $fontName): self;

    public function useDefaultFont(): self;

    public function setFontByName(string $fontName): self;

    // Gravatar methods
    public function getGravatar(int $size = 200, bool $isHttps = false, string $rating = 'g', string $default = 'monsterid'): ?string;

    public function getGravatarForEmail(string $email, int $size = 200, bool $isHttps = false, string $rating = 'g', string $default = 'monsterid'): string;

    public function gravatar(?string $email = null): GravatarServiceInterface;

    public function generateWithGravatarFallback(int $size = 200, bool $preferGravatar = true): string;

    public function hasGravatar(): bool;

    /** @return list<string> */
    public function getGravatarRatings(): array;

    /** @return list<string> */
    public function getGravatarDefaultImages(): array;

    // Avatar resolution methods
    /** @param array<string, mixed> $options */
    public function getAvatar(string|Model|callable $source, array $options = []): AvatarResolution;

    /** @param array<string, mixed> $options */
    public function getAvatarUrl(string|Model|callable $source, array $options = []): string;

    /** @param array<string, mixed> $options */
    public function getAvatarCached(string|Model|callable $source, array $options = [], ?int $ttl = null): AvatarResolution;
}
