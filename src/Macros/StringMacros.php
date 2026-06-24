<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Registers the toolkit's general-purpose Str and Stringable macros.
 */
final class StringMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerStrMacros();
        $this->registerStringableMacros();
    }

    private function registerStrMacros(): void
    {
        Str::macro('kebabToTitle', fn (string $string): string => Str::title(Str::replace('-', ' ', $string)));

        Str::macro('snakeToTitle', fn (string $string): string => Str::title(Str::replace('_', ' ', $string)));

        Str::macro('camelToTitle', fn (string $string): string => Str::title(Str::snake($string, ' ')));

        Str::macro('truncateMiddle', function (string $string, int $length = 50, string $middle = '...'): string {
            if (Str::length($string) <= $length) {
                return $string;
            }

            $middleLength = Str::length($middle);
            $halfLength = (int) (($length - $middleLength) / 2);

            return Str::substr($string, 0, $halfLength) . $middle . Str::substr($string, -$halfLength);
        });

        Str::macro('isEmail', fn (string $string): bool => filter_var($string, FILTER_VALIDATE_EMAIL) !== false);

        Str::macro('stripWhitespace', fn (string $string): string => (string) preg_replace('/\s+/', '', $string));

        Str::macro('normalizeWhitespace', fn (string $string): string => (string) preg_replace('/\s+/', ' ', trim($string)));

        Str::macro('toBool', function (string $string): bool {
            $string = Str::lower(trim($string));

            return in_array($string, ['1', 'true', 'yes', 'on'], true);
        });

        Str::macro('wrapWith', fn (string $string, string $wrapper = '"'): string => $wrapper . $string . $wrapper);

        Str::macro('replaceMany', function (string $string, array $replacements): string {
            foreach ($replacements as $search => $replace) {
                $string = Str::replace((string) $search, (string) $replace, $string);
            }

            return $string;
        });

        // Whether the string matches a full PCRE pattern. The legacy macro
        // delegated to a non-existent Str::matches() and double-wrapped the
        // result; this is a direct, bool-returning preg_match() wrapper.
        Str::macro('matches', fn (string $string, string $pattern): bool => preg_match($pattern, $string) === 1);

        Str::macro('reverseString', fn (string $string): string => strrev($string));

        Str::macro('countWords', fn (string $string): int => str_word_count($string));

        Str::macro('removeAccents', function (string $string): string {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);

            return $converted === false ? $string : $converted;
        });

        // Estimated reading time in (rounded-up) minutes for a body of text.
        Str::macro('readingMinutes', function (string $string, int $wordsPerMinute = 200): int {
            $wordsPerMinute = max(1, $wordsPerMinute);
            $words = str_word_count(strip_tags($string));

            return max(1, (int) ceil($words / $wordsPerMinute));
        });

        // Wrap each occurrence of the given term(s) in <mark>…</mark>. The input
        // text is e()-escaped first and only the matched terms are wrapped, so
        // the result is always safe HTML — returned as an HtmlString.
        Str::macro('highlightWords', function (string $string, string|array $words): HtmlString {
            $terms = array_filter(
                array_map(static fn (mixed $word): string => trim(Cast::toString($word)), (array) $words),
                static fn (string $word): bool => $word !== '',
            );

            $escaped = e($string);

            if ($terms === []) {
                return new HtmlString($escaped);
            }

            // Match against the already-escaped haystack using escaped needles so
            // terms containing HTML-special chars (", &, <, >) still highlight.
            $pattern = '/(' . implode('|', array_map(
                static fn (string $term): string => preg_quote(e($term), '/'),
                $terms,
            )) . ')/iu';

            $highlighted = preg_replace($pattern, '<mark>$1</mark>', $escaped);

            return new HtmlString($highlighted ?? $escaped);
        });
    }

    private function registerStringableMacros(): void
    {
        Stringable::macro('kebabToTitle', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::kebabToTitle((string) $this));
        });

        Stringable::macro('snakeToTitle', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::snakeToTitle((string) $this));
        });

        Stringable::macro('camelToTitle', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::camelToTitle((string) $this));
        });

        Stringable::macro('truncateMiddle', function (int $length = 50, string $middle = '...'): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::truncateMiddle((string) $this, $length, $middle));
        });

        Stringable::macro('isEmail', function (): bool {
            /** @var Stringable $this */
            return Str::isEmail((string) $this);
        });

        Stringable::macro('stripWhitespace', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::stripWhitespace((string) $this));
        });

        Stringable::macro('normalizeWhitespace', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::normalizeWhitespace((string) $this));
        });

        Stringable::macro('toBool', function (): bool {
            /** @var Stringable $this */
            return Str::toBool((string) $this);
        });

        Stringable::macro('wrapWith', function (string $wrapper = '"'): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::wrapWith((string) $this, $wrapper));
        });

        Stringable::macro('matches', function (string $pattern): bool {
            /** @var Stringable $this */
            return Str::matches((string) $this, $pattern);
        });

        Stringable::macro('reverseString', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::reverseString((string) $this));
        });

        Stringable::macro('countWords', function (): int {
            /** @var Stringable $this */
            return Str::countWords((string) $this);
        });

        Stringable::macro('removeAccents', function (): Stringable {
            /** @var Stringable $this */
            return new Stringable(Str::removeAccents((string) $this));
        });

        Stringable::macro('readingMinutes', function (int $wordsPerMinute = 200): int {
            /** @var Stringable $this */
            return Str::readingMinutes((string) $this, $wordsPerMinute);
        });

        Stringable::macro('highlightWords', function (string|array $words): HtmlString {
            /** @var Stringable $this */
            return Str::highlightWords((string) $this, $words);
        });
    }
}
