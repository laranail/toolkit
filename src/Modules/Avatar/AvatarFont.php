<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar;

/**
 * Bundled fonts available for avatar text rendering.
 *
 * A plain, native PHP 8.3 backed enum. Each case is backed by the `.ttf`
 * filename of the bundled font shipped under `resources/assets/fonts/`.
 *
 * All metadata (display name, description, category, unicode support) is
 * resolved natively via `match()` expressions — there is no dependency on any
 * external enum/enumerator package.
 */
enum AvatarFont: string
{
    case ROBOTO_BOLD = 'Roboto-Bold.ttf';

    case FREE_SERIF = 'FreeSerif.ttf';

    case MSYH = 'msyh.ttf';

    /**
     * The default font shipped with the package.
     */
    public static function default(): self
    {
        return self::ROBOTO_BOLD;
    }

    /**
     * Look up a case by its backing value (file name), returning null when the
     * value is not a known font.
     */
    public static function fromValueOrNull(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }

    /**
     * Case constant names (e.g. ROBOTO_BOLD, FREE_SERIF, MSYH).
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $font): string => $font->name, self::cases());
    }

    /**
     * Backing values (the `.ttf` file names).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $font): string => $font->value, self::cases());
    }

    /**
     * Cases that match the given category.
     *
     * @return list<self>
     */
    public static function getByCategory(string $category): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $font): bool => $font->getCategory() === $category,
        ));
    }

    /**
     * Human-readable display name.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::ROBOTO_BOLD => 'Roboto Bold',
            self::FREE_SERIF => 'Free Serif',
            self::MSYH => 'Microsoft YaHei',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::ROBOTO_BOLD => 'Modern, clean sans-serif font (default).',
            self::FREE_SERIF => 'Classic serif font for formal documents.',
            self::MSYH => 'Chinese font for international support.',
        };
    }

    public function getCategory(): string
    {
        return match ($this) {
            self::ROBOTO_BOLD => 'sans-serif',
            self::FREE_SERIF => 'serif',
            self::MSYH => 'international',
        };
    }

    public function supportsUnicode(): bool
    {
        return match ($this) {
            self::ROBOTO_BOLD, self::FREE_SERIF, self::MSYH => true,
        };
    }

    /**
     * The backing value (font file name).
     */
    public function getValue(): string
    {
        return $this->value;
    }
}
