<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Laravel\Blade;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the toolkit's genuinely-custom Blade directives.
 *
 * Directives that merely re-implement a native Laravel 11–13 directive
 * (`@checked`, `@selected`, `@disabled`, `@readonly`, `@required`, `@class`,
 * `@style`, `@session`, `@vite`, `@js`, `@error`, `@once`, `@pushOnce`,
 * `@prepend`, `@prependOnce`, `@fragment`, `@aware`, `@props`, `@dump`,
 * `@dd`, `@mix`/`@vite`) are intentionally not registered here. Only
 * directives with no native counterpart remain.
 *
 * Registered eagerly from the root provider's boot().
 */
class BladeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerConditionalDirectives();
        $this->registerRouteDirectives();
        $this->registerTypeDirectives();
        $this->registerIconDirectives();
        $this->registerAssetDirectives();
    }

    /**
     * @istrue / @isfalse / @isnull / @isnotnull — null-safe truthiness blocks
     * with no native equivalent.
     */
    private function registerConditionalDirectives(): void
    {
        Blade::directive('istrue', static fn (string $expression): string => "<?php if (isset({$expression}) && (bool) ({$expression}) === true) : ?>");
        Blade::directive('endistrue', static fn (): string => '<?php endif; ?>');

        Blade::directive('isfalse', static fn (string $expression): string => "<?php if (isset({$expression}) && (bool) ({$expression}) === false) : ?>");
        Blade::directive('endisfalse', static fn (): string => '<?php endif; ?>');

        Blade::directive('isnull', static fn (string $expression): string => "<?php if (is_null({$expression})) : ?>");
        Blade::directive('endisnull', static fn (): string => '<?php endif; ?>');

        Blade::directive('isnotnull', static fn (string $expression): string => "<?php if (! is_null({$expression})) : ?>");
        Blade::directive('endisnotnull', static fn (): string => '<?php endif; ?>');
    }

    /**
     * @routeis / @routeisnot — fnmatch-based current-route checks, and
     *
     * @activeifroute — emit "active" when the current route name matches.
     */
    private function registerRouteDirectives(): void
    {
        Blade::directive('routeis', static fn (string $expression): string => "<?php if (fnmatch({$expression}, (string) \\Illuminate\\Support\\Facades\\Route::currentRouteName())) : ?>");
        Blade::directive('endrouteis', static fn (): string => '<?php endif; ?>');

        Blade::directive('routeisnot', static fn (string $expression): string => "<?php if (! fnmatch({$expression}, (string) \\Illuminate\\Support\\Facades\\Route::currentRouteName())) : ?>");
        Blade::directive('endrouteisnot', static fn (): string => '<?php endif; ?>');

        Blade::directive('activeifroute', static fn (string $expression): string => "<?php echo str_starts_with((string) \\Illuminate\\Support\\Facades\\Route::currentRouteName(), {$expression}) ? 'active' : ''; ?>");
    }

    /**
     * @instanceof / @typeof — type assertions with no native counterpart.
     */
    private function registerTypeDirectives(): void
    {
        Blade::directive('instanceof', static function (string $expression): string {
            [$value, $class] = self::twoArgs($expression);

            return "<?php if ({$value} instanceof {$class}) : ?>";
        });
        Blade::directive('endinstanceof', static fn (): string => '<?php endif; ?>');

        Blade::directive('typeof', static function (string $expression): string {
            [$value, $type] = self::twoArgs($expression);

            return "<?php if (gettype({$value}) === {$type}) : ?>";
        });
        Blade::directive('endtypeof', static fn (): string => '<?php endif; ?>');

        Blade::directive('repeat', static fn (string $expression): string => "<?php for (\$__repeat = 0; \$__repeat < (int) ({$expression}); \$__repeat++) : ?>");
        Blade::directive('endrepeat', static fn (): string => '<?php endfor; ?>');
    }

    /**
     * Icon shorthands (@fa, @fas, @far, @fal, @fab, @fad, @mdi, @glyph, @bi).
     */
    private function registerIconDirectives(): void
    {
        $iconFamilies = [
            'fa' => 'fa fa-',
            'fas' => 'fas fa-',
            'far' => 'far fa-',
            'fal' => 'fal fa-',
            'fab' => 'fab fa-',
            'fad' => 'fad fa-',
            'mdi' => 'mdi mdi-',
            'glyph' => 'glyphicons glyphicons-',
            'bi' => 'bi bi-',
        ];

        foreach ($iconFamilies as $name => $prefix) {
            Blade::directive($name, static function (string $expression) use ($prefix): string {
                [$icon, $classes] = self::twoArgs($expression);
                $icon = self::stripQuotes($icon);
                $classes = self::stripQuotes($classes);

                return '<i class="' . $prefix . trim($icon . ' ' . $classes) . '"></i>';
            });
        }
    }

    /**
     * @window — expose a value on the JS `window`, and @base64image — inline an
     * image file as a data URI. No native equivalents.
     */
    private function registerAssetDirectives(): void
    {
        Blade::directive('window', static function (string $expression): string {
            [$name, $value] = self::twoArgs($expression);
            $variable = self::stripQuotes($name);

            return implode("\n", [
                '<script>',
                "window.{$variable} = <?php echo is_array({$value}) ? json_encode({$value}) : {$value}; ?>;",
                '</script>',
            ]);
        });

        Blade::directive('base64image', static fn (string $expression): string => "<?php echo 'data:image/' . pathinfo({$expression}, PATHINFO_EXTENSION) . ';base64,' . base64_encode((string) file_get_contents({$expression})); ?>");
    }

    /**
     * Split a two-argument directive expression into its trimmed parts.
     *
     * @return array{0: string, 1: string}
     */
    private static function twoArgs(string $expression): array
    {
        $parts = array_map('trim', explode(',', $expression, 2));

        return [$parts[0], $parts[1] ?? ''];
    }

    private static function stripQuotes(string $expression): string
    {
        return trim(str_replace(['\'', '"'], '', $expression));
    }
}
