<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use InvalidArgumentException;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;
use Throwable;

/**
 * Fluent, chainable avatar generation service.
 *
 * Generates avatar images from names, text or e-mail addresses, with optional
 * Gravatar integration. The service is bound by contract and resolved through
 * the container; the {@see GravatarServiceInterface} dependency is injected for
 * the Gravatar-integration methods.
 */
class AvatarService implements AvatarServiceInterface
{
    /**
     * Available background colors for avatar generation.
     *
     * @var list<string>
     */
    protected static array $availableBackgrounds = [
        '#f44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5',
        '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50',
        '#8BC34A', '#CDDC39', '#FFC107', '#FF9800', '#FF5722',
        '#795548', '#607D8B', '#FFEB3B', '#FFC0CB', '#E0E0E0',
    ];

    /**
     * Available foreground colors for avatar generation.
     *
     * @var list<string>
     */
    protected static array $availableForegrounds = [
        '#FFFFFF', '#000000', '#333333', '#666666',
    ];

    /**
     * Available shapes for avatar generation.
     *
     * @var list<string>
     */
    protected static array $availableShapes = ['circle', 'square'];

    /**
     * Pattern matching a valid 3- or 6-digit hex color.
     */
    private const HEX_COLOR_PATTERN = '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    protected ?string $name = null;

    protected int $width = 250;

    protected int $height = 250;

    protected string $shape = 'circle';

    protected string $backgroundColor = '#CCCCCC';

    protected string $foregroundColor = '#FFFFFF';

    protected int $chars = 1;

    protected int $fontSize = 152;

    protected int $borderSize = 0;

    protected string $borderColor = 'foreground';

    protected bool $uppercase = false;

    protected bool $ascii = false;

    protected bool $cacheEnabled = true;

    protected int $cacheTtl = 86400;

    protected string $fontPath = '';

    protected int $quality = 90;

    protected AvatarFont $defaultFont = AvatarFont::ROBOTO_BOLD;

    public function __construct(
        protected Application $app,
        protected Filesystem $files,
        protected GravatarServiceInterface $gravatar,
        protected CacheRepository $cache,
    ) {
        $this->fontPath = $this->getDefaultFontPath();
        $this->backgroundColor = $this->getRandomBackgroundColor();
        $this->foregroundColor = $this->getRandomForegroundColor();
    }

    // Fluent setters

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function setSize(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function setShape(string $shape): self
    {
        if (!in_array($shape, self::$availableShapes, true)) {
            throw new InvalidArgumentException(
                "Shape '{$shape}' is not supported. Available shapes: " . implode(', ', self::$availableShapes),
            );
        }

        $this->shape = $shape;

        return $this;
    }

    public function setBackgroundColor(string $color): self
    {
        $this->backgroundColor = $this->validateColor($color);

        return $this;
    }

    public function setForegroundColor(string $color): self
    {
        $this->foregroundColor = $this->validateColor($color);

        return $this;
    }

    public function setColors(string $backgroundColor, string $foregroundColor): self
    {
        $this->backgroundColor = $this->validateColor($backgroundColor);
        $this->foregroundColor = $this->validateColor($foregroundColor);

        return $this;
    }

    public function setChars(int $chars): self
    {
        $this->chars = $chars;

        return $this;
    }

    public function setFontSize(int $size): self
    {
        $this->fontSize = $size;

        return $this;
    }

    public function setBorderSize(int $size): self
    {
        $this->borderSize = $size;

        return $this;
    }

    public function setBorderColor(string $color): self
    {
        // The border color may be one of the symbolic keywords or a hex value.
        if ($color === 'foreground' || $color === 'background') {
            $this->borderColor = $color;

            return $this;
        }

        $this->borderColor = $this->validateColor($color);

        return $this;
    }

    public function setUppercase(bool $uppercase): self
    {
        $this->uppercase = $uppercase;

        return $this;
    }

    public function setAscii(bool $ascii): self
    {
        $this->ascii = $ascii;

        return $this;
    }

    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;

        return $this;
    }

    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    public function setFontPath(string $path): self
    {
        if (!$this->files->exists($path)) {
            throw new InvalidArgumentException("Font file does not exist: {$path}");
        }

        $this->fontPath = $path;

        return $this;
    }

    public function setQuality(int $quality): self
    {
        if ($quality < 1 || $quality > 100) {
            throw new InvalidArgumentException('Quality must be between 1 and 100');
        }

        $this->quality = $quality;

        return $this;
    }

    // Getters

    public function getName(): ?string
    {
        return $this->name;
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

    public function getBackgroundColor(): string
    {
        return $this->backgroundColor;
    }

    public function getForegroundColor(): string
    {
        return $this->foregroundColor;
    }

    public function getChars(): int
    {
        return $this->chars;
    }

    public function getFontSize(): int
    {
        return $this->fontSize;
    }

    public function getBorderSize(): int
    {
        return $this->borderSize;
    }

    public function getBorderColor(): string
    {
        return $this->borderColor;
    }

    public function isUppercase(): bool
    {
        return $this->uppercase;
    }

    public function isAscii(): bool
    {
        return $this->ascii;
    }

    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function getFontPath(): string
    {
        return $this->fontPath;
    }

    public function getQuality(): int
    {
        return $this->quality;
    }

    // Generation methods

    public function generate(): string
    {
        return $this->generateDataUri();
    }

    public function generateBase64(): string
    {
        $cacheKey = $this->getCacheKey();

        if ($this->cacheEnabled) {
            $cached = $this->cache->get($cacheKey);
            if (is_string($cached)) {
                return $cached;
            }
        }

        try {
            $image = $this->createAvatarImage();
            $dataUri = (string) $image->encode(new PngEncoder())->toDataUri();

            if ($this->cacheEnabled) {
                $this->cache->put($cacheKey, $dataUri, $this->cacheTtl);
            }

            return $dataUri;
        } catch (Throwable) {
            return $this->getDefaultAvatar();
        }
    }

    public function generateDataUri(): string
    {
        return $this->generateBase64();
    }

    public function save(string $path): bool
    {
        try {
            $image = $this->createAvatarImage();

            $directory = dirname($path);
            if (!$this->files->exists($directory)) {
                $this->files->makeDirectory($directory, 0755, true);
            }

            $image->save($path, $this->quality);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function getImageObject(): ImageInterface
    {
        return $this->createAvatarImage();
    }

    public function makeInitials(): string
    {
        if ($this->name === null || $this->name === '') {
            return Str::upper(chr(random_int(65, 90)));
        }

        $processedName = $this->processName($this->name);
        $words = array_values(array_filter(explode(' ', $processedName), static fn (string $word): bool => $word !== ''));

        if (count($words) <= 1) {
            $single = $words[0] ?? $processedName;
            $initial = Str::length($single) >= $this->chars
                ? Str::substr($single, 0, $this->chars)
                : $single;
        } else {
            $initials = array_map(static fn (string $word): string => Str::substr($word, 0, 1), $words);
            $initial = implode('', array_slice($initials, 0, $this->chars));
        }

        return $this->uppercase ? Str::upper($initial) : $initial;
    }

    // Utility methods

    public function getRandomBackgroundColor(): string
    {
        $name = $this->name ?? chr(random_int(65, 90));
        $hash = $this->generateHashFromName($name);

        return self::$availableBackgrounds[$hash % count(self::$availableBackgrounds)];
    }

    public function getRandomForegroundColor(): string
    {
        $name = $this->name ?? chr(random_int(65, 90));
        $hash = $this->generateHashFromName($name);

        return self::$availableForegrounds[$hash % count(self::$availableForegrounds)];
    }

    public function isImageProcessingAvailable(): bool
    {
        return extension_loaded('gd') || extension_loaded('imagick');
    }

    /**
     * @return list<string>
     */
    public function getAvailableShapes(): array
    {
        return self::$availableShapes;
    }

    /**
     * @return list<string>
     */
    public function getAvailableBackgroundColors(): array
    {
        return self::$availableBackgrounds;
    }

    /**
     * @return list<string>
     */
    public function getAvailableForegroundColors(): array
    {
        return self::$availableForegrounds;
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableFonts(): array
    {
        $availableFonts = [];

        foreach (AvatarFont::cases() as $font) {
            foreach ($this->getFontPaths($font->value) as $fontPath) {
                if ($this->files->exists($fontPath)) {
                    $availableFonts[$font->value] = $fontPath;
                    break;
                }
            }
        }

        return $availableFonts;
    }

    /**
     * @return list<string>
     */
    public function getAvailableFontNames(): array
    {
        return AvatarFont::names();
    }

    /**
     * @return list<string>
     */
    public function getAvailableFontValues(): array
    {
        return AvatarFont::values();
    }

    /**
     * @return list<AvatarFont>
     */
    public function getAvailableFontEnums(): array
    {
        return AvatarFont::cases();
    }

    public function getDefaultFont(): AvatarFont
    {
        return $this->defaultFont;
    }

    public function getDefaultFontName(): string
    {
        return $this->defaultFont->value;
    }

    public function setDefaultFont(AvatarFont $font): self
    {
        $this->defaultFont = $font;

        return $this;
    }

    public function setDefaultFontName(string $fontName): self
    {
        $font = AvatarFont::fromValueOrNull($fontName);

        if ($font === null) {
            throw new InvalidArgumentException(
                "Font '{$fontName}' is not available. Available fonts: " . implode(', ', $this->getAvailableFontValues()),
            );
        }

        $this->defaultFont = $font;

        return $this;
    }

    public function useFont(AvatarFont $font): self
    {
        foreach ($this->getFontPaths($font->value) as $fontPath) {
            if ($this->files->exists($fontPath)) {
                $this->fontPath = $fontPath;

                return $this;
            }
        }

        return $this->useDefaultFont();
    }

    public function useFontByName(string $fontName): self
    {
        $font = AvatarFont::fromValueOrNull($fontName);

        if ($font === null) {
            throw new InvalidArgumentException(
                "Font '{$fontName}' is not available. Available fonts: " . implode(', ', $this->getAvailableFontValues()),
            );
        }

        return $this->useFont($font);
    }

    public function useDefaultFont(): self
    {
        foreach ($this->getFontPaths($this->defaultFont->value) as $fontPath) {
            if ($this->files->exists($fontPath)) {
                $this->fontPath = $fontPath;

                return $this;
            }
        }

        $this->fontPath = $this->getDefaultFontPath();

        return $this;
    }

    public function setFontByName(string $fontName): self
    {
        if (!in_array($fontName, AvatarFont::values(), true)) {
            throw new InvalidArgumentException(
                "Font '{$fontName}' is not available. Available fonts: " . implode(', ', AvatarFont::values()),
            );
        }

        foreach ($this->getFontPaths($fontName) as $fontPath) {
            if ($this->files->exists($fontPath)) {
                $this->fontPath = $fontPath;

                return $this;
            }
        }

        throw new InvalidArgumentException(
            "Font '{$fontName}' files not found in any location. Place the .ttf in "
            . 'public/vendor/laranail/fonts/ or resources/assets/fonts/, or set an absolute '
            . 'path with setFontPath().',
        );
    }

    // Gravatar methods

    public function getGravatar(int $size = 200, bool $isHttps = false, string $rating = 'g', string $default = 'monsterid'): ?string
    {
        $email = $this->getEmailFromName();

        if ($email === null) {
            return null;
        }

        return $this->getGravatarForEmail($email, $size, $isHttps, $rating, $default);
    }

    public function getGravatarForEmail(string $email, int $size = 200, bool $isHttps = false, string $rating = 'g', string $default = 'monsterid'): string
    {
        return $this->gravatar
            ->setEmail($email)
            ->setSize($size)
            ->setHttps($isHttps)
            ->setRating($rating)
            ->setDefaultImage($default)
            ->setForceDefault(false)
            ->generate();
    }

    public function gravatar(?string $email = null): GravatarServiceInterface
    {
        $resolved = $email ?? $this->getEmailFromName();

        if ($resolved !== null) {
            return $this->gravatar->setEmail($resolved);
        }

        return $this->gravatar;
    }

    public function generateWithGravatarFallback(int $size = 200, bool $preferGravatar = true): string
    {
        $email = $this->getEmailFromName();

        if ($preferGravatar && $email !== null) {
            $gravatarUrl = $this->getGravatar($size, true);
            if ($gravatarUrl !== null) {
                return $gravatarUrl;
            }
        }

        return $this->setSize($size, $size)->generate();
    }

    public function hasGravatar(): bool
    {
        $email = $this->getEmailFromName();

        return $email !== null && $this->gravatar->isValidEmail($email);
    }

    /**
     * @return list<string>
     */
    public function getGravatarRatings(): array
    {
        return $this->gravatar->availableRatings();
    }

    /**
     * @return list<string>
     */
    public function getGravatarDefaultImages(): array
    {
        return $this->gravatar->availableDefaultImages();
    }

    // Avatar resolution methods

    /**
     * @param array<string, mixed> $options
     */
    public function getAvatar(string|Model|callable $source, array $options = []): AvatarResolution
    {
        $resolver = new AvatarResolver($this, $this->gravatar);

        if (isset($options['field_mappings']) && is_array($options['field_mappings'])) {
            /** @var array<string, list<string>> $mappings */
            $mappings = $options['field_mappings'];
            $resolver->setFieldMappings($mappings);
        }

        if (isset($options['config']) && is_array($options['config'])) {
            /** @var array<string, mixed> $config */
            $config = $options['config'];
            $resolver->setConfig($config);
        }

        $resolvable = is_callable($source) && !$source instanceof Model
            ? \Closure::fromCallable($source)
            : $source;

        return $resolver->resolve($resolvable, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getAvatarUrl(string|Model|callable $source, array $options = []): string
    {
        return $this->getAvatar($source, $options)->getUrl();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getAvatarCached(string|Model|callable $source, array $options = [], ?int $ttl = null): AvatarResolution
    {
        $cacheKey = $this->getAvatarCacheKey($source, $options);
        $ttl ??= isset($options['cache_ttl']) ? Cast::toInt($options['cache_ttl'], 3600) : 3600;

        return $this->cache->remember(
            $cacheKey,
            $ttl,
            fn (): AvatarResolution => $this->getAvatar($source, $options),
        );
    }

    // Protected methods

    /**
     * Build a fresh blank canvas using the best available driver (Imagick when
     * loaded, otherwise GD).
     */
    protected function newImage(): ImageInterface
    {
        $driver = extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class;

        /** @var DriverInterface $instance */
        $instance = $this->app->make($driver);

        return $instance->createImage($this->width, $this->height);
    }

    protected function createAvatarImage(): ImageInterface
    {
        if (!$this->isImageProcessingAvailable()) {
            throw new RuntimeException('Image processing is not available. GD or Imagick extension is required.');
        }

        $image = $this->newImage();

        $this->createShape($image);

        // BUG FIX: render the initials text whenever a usable font file exists,
        // regardless of the active driver or the environment. The legacy code
        // only drew text under Imagick or in the `local` environment, producing
        // blank avatars on GD-only production hosts.
        if ($this->fontPath !== '' && $this->files->exists($this->fontPath)) {
            $this->addTextToImage($image, $this->makeInitials());
        }

        return $image;
    }

    protected function createShape(ImageInterface $image): void
    {
        match ($this->shape) {
            'circle' => $this->createCircleShape($image),
            'square' => $this->createSquareShape($image),
            default => throw new InvalidArgumentException("Shape '{$this->shape}' is not supported"),
        };
    }

    protected function createCircleShape(ImageInterface $image): void
    {
        // BUG FIX: the legacy radius used the full width, overflowing the
        // canvas. Use half the width (a true radius), inset by the border so
        // the circle fits within the canvas bounds.
        $radius = max(1, (int) ($this->width / 2) - $this->borderSize);
        $centerX = (int) ($this->width / 2);
        $centerY = (int) ($this->height / 2);

        $image->drawCircle(function (CircleFactory $draw) use ($radius, $centerX, $centerY): void {
            $draw->at($centerX, $centerY);
            $draw->radius($radius);
            $draw->background($this->backgroundColor);
            $draw->border($this->getBorderColorValue(), $this->borderSize);
        });
    }

    protected function createSquareShape(ImageInterface $image): void
    {
        $edge = (int) ceil($this->borderSize / 2);
        $rectWidth = $this->width - $edge;
        $rectHeight = $this->height - $edge;

        $image->drawRectangle(function (RectangleFactory $draw) use ($edge, $rectWidth, $rectHeight): void {
            $draw->at($edge, $edge);
            $draw->size($rectWidth, $rectHeight);
            $draw->background($this->backgroundColor);
            $draw->border($this->getBorderColorValue(), $this->borderSize);
        });
    }

    protected function addTextToImage(ImageInterface $image, string $text): void
    {
        $image->text(
            $text,
            (int) ($this->width / 2),
            (int) ($this->height / 2),
            function (FontFactory $font): void {
                $font->filename($this->fontPath);
                $font->size($this->fontSize);
                $font->color($this->foregroundColor);
                $font->align('center', 'center');
            },
        );
    }

    protected function processName(string $name): string
    {
        if (filter_var($name, FILTER_VALIDATE_EMAIL)) {
            $name = str_replace('.', ' ', Str::before($name, '@'));
        }

        if ($this->ascii) {
            $name = Str::ascii($name);
        }

        return trim($name);
    }

    /**
     * Produce a stable, deterministic hash for a name.
     *
     * BUG FIX: the legacy implementation indexed string bytes using a
     * multibyte-aware length bound (`Str::length`), so UTF-8 names produced
     * wrong or empty hashes. `crc32` operates on the raw byte string and is
     * deterministic across requests for any input, including multibyte names.
     */
    protected function generateHashFromName(string $name): int
    {
        return (int) crc32($name);
    }

    protected function getBorderColorValue(): string
    {
        return match ($this->borderColor) {
            'foreground' => $this->foregroundColor,
            'background' => $this->backgroundColor,
            default => $this->borderColor,
        };
    }

    /**
     * Possible on-disk locations for a font, in priority order.
     *
     * @return list<string>
     */
    protected function getFontPaths(string $fontName): array
    {
        $paths = [
            // Bundled package fonts (the canonical source).
            __DIR__ . '/../../../resources/assets/fonts/' . $fontName,
        ];

        if (function_exists('public_path')) {
            $paths[] = public_path("vendor/laranail/fonts/{$fontName}");
        }

        if (function_exists('resource_path')) {
            $paths[] = resource_path("assets/fonts/{$fontName}");
        }

        return $paths;
    }

    /**
     * Resolve a usable default font path, falling back across bundled fonts.
     */
    protected function getDefaultFontPath(): string
    {
        foreach ($this->getFontPaths($this->defaultFont->value) as $fontPath) {
            if ($this->files->exists($fontPath)) {
                return $fontPath;
            }
        }

        foreach (AvatarFont::values() as $fontValue) {
            foreach ($this->getFontPaths($fontValue) as $fontPath) {
                if ($this->files->exists($fontPath)) {
                    return $fontPath;
                }
            }
        }

        throw new RuntimeException('No suitable font found for avatar generation. The package ships bundled fonts under resources/assets/fonts/ — reinstall the package if they are missing, or set an absolute path with setFontPath().');
    }

    protected function getCacheKey(): string
    {
        $attributes = [
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'shape' => $this->shape,
            'chars' => $this->chars,
            'background' => $this->backgroundColor,
            'foreground' => $this->foregroundColor,
            'font_size' => $this->fontSize,
            'border_size' => $this->borderSize,
            'border_color' => $this->borderColor,
            'uppercase' => $this->uppercase,
            'ascii' => $this->ascii,
            'font' => $this->fontPath,
        ];

        $filtered = array_filter(
            $attributes,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return 'avatar_' . md5(implode('-', array_map(
            static fn (mixed $value): string => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            $filtered,
        )));
    }

    /**
     * Extract an e-mail from the configured name, when it looks like one.
     */
    protected function getEmailFromName(): ?string
    {
        if ($this->name === null || $this->name === '') {
            return null;
        }

        if (filter_var($this->name, FILTER_VALIDATE_EMAIL)) {
            return $this->name;
        }

        if (preg_match('/^(.+)\s*<(.+)>$/', $this->name, $matches)) {
            $email = trim($matches[2]);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    protected function getDefaultAvatar(): string
    {
        try {
            $image = $this->newImage();

            $image->drawRectangle(function (RectangleFactory $draw): void {
                $draw->at(0, 0);
                $draw->size($this->width, $this->height);
                $draw->background('#CCCCCC');
            });

            return (string) $image->encode(new PngEncoder())->toDataUri();
        } catch (Throwable) {
            // Only validated integers are interpolated into the SVG fallback.
            $svg = '<svg width="' . $this->width . '" height="' . $this->height
                . '" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#CCCCCC"/></svg>';

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function getAvatarCacheKey(string|Model|callable $source, array $options): string
    {
        $key = 'avatar_resolved_';

        if (is_string($source)) {
            $key .= 'string_' . md5($source);
        } elseif ($source instanceof Model) {
            $key .= 'model_' . $source->getMorphClass() . '_' . Cast::toString($source->getKey());
        } else {
            $key .= 'callback_' . spl_object_hash(\Closure::fromCallable($source));
        }

        return $key . '_' . md5(serialize($options));
    }

    /**
     * Validate and normalise a hex color string.
     *
     * BUG FIX: the legacy setters accepted any string unchecked, allowing
     * unvalidated values to flow into image drawing / the SVG fallback. Colors
     * must now be 3- or 6-digit hex values.
     */
    private function validateColor(string $color): string
    {
        if (preg_match(self::HEX_COLOR_PATTERN, $color) !== 1) {
            throw new InvalidArgumentException(
                "Invalid hex color '{$color}'. Expected a 3- or 6-digit hex value, e.g. '#FFF' or '#FF5722'.",
            );
        }

        return $color;
    }
}
