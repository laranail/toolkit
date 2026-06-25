<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Atlas;

/**
 * Contract for the Atlas module — a clean façade over the country/locale data
 * package (rinvex/countries) plus a slim Laravel-locale registry.
 *
 * Countries, currencies and timezones are sourced from the data package;
 * languages/locales come from the package's `config/languages.php` config
 * (the data package is country-centric, not locale-centric).
 *
 * @phpstan-type CountrySummary array{name: string, official_name: string, native_name: string, iso2: string, iso3: string, currency: string, calling_code: string, emoji: string}
 * @phpstan-type LanguageEntry array{iso639_1: string, locale: string, native_name: string, dir: string, flag: string}
 */
interface AtlasServiceInterface
{
    /**
     * All countries as a list of compact summaries, keyed by upper-case ISO2.
     *
     * @return array<string, CountrySummary>
     */
    public function countries(): array;

    /**
     * A single country summary resolved by ISO 3166-1 alpha-2 or alpha-3 code
     * (case-insensitive), or null when the code is unknown.
     *
     * @return CountrySummary|null
     */
    public function country(string $code): ?array;

    /**
     * A `code => label` map for an HTML `<select>` box.
     *
     * @param 'name'|'official_name'|'native_name' $label Which name to use as the label.
     * @param bool                                 $iso3  Key by ISO3 instead of ISO2.
     *
     * @return array<string, string>
     */
    public function forSelectBox(string $label = 'name', bool $iso3 = false): array;

    /**
     * All ISO 4217 currency codes present across the dataset (sorted).
     *
     * @return array<int, string>
     */
    public function currencies(): array;

    /**
     * All IANA timezone identifiers (sorted).
     *
     * @return array<int, string>
     */
    public function timezones(): array;

    /**
     * The full language/locale registry, keyed by Laravel locale.
     *
     * @return array<string, LanguageEntry>
     */
    public function languages(): array;

    /**
     * The list of supported Laravel locale codes.
     *
     * @return array<int, string>
     */
    public function locales(): array;

    /**
     * The subset of registered locales that have a translation directory under
     * `resource_path('lang')`, each as a slim `{locale, name, flag}` tuple.
     *
     * @return array<string, array{locale: string, name: string, flag: string}>
     */
    public function availableLocales(): array;
}
