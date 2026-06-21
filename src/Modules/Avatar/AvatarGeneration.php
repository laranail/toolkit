<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar;

/**
 * Immutable metadata describing a generated avatar image.
 */
readonly class AvatarGeneration implements \Stringable
{
    /**
     * @param array<string, string> $colors
     * @param array<string, mixed>  $metadata
     */
    public function __construct(
        public string $url,
        public string $format,
        public int $width,
        public int $height,
        public string $shape,
        public array $colors,
        public array $metadata = [],
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getShape(): string
    {
        return $this->shape;
    }

    /**
     * @return array<string, string>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isSquare(): bool
    {
        return $this->width === $this->height;
    }

    public function isCircular(): bool
    {
        return $this->shape === 'circle';
    }

    public function getAspectRatio(): float
    {
        return $this->width / $this->height;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->colors['background'] ?? null;
    }

    public function getForegroundColor(): ?string
    {
        return $this->colors['foreground'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'format' => $this->format,
            'width' => $this->width,
            'height' => $this->height,
            'shape' => $this->shape,
            'colors' => $this->colors,
            'metadata' => $this->metadata,
            'is_square' => $this->isSquare(),
            'is_circular' => $this->isCircular(),
            'aspect_ratio' => $this->getAspectRatio(),
        ];
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
