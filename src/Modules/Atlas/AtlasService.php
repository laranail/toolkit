<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Atlas;

use DateTimeZone;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Rinvex\Country\CountryLoader;
use Rinvex\Country\CurrencyLoader;
use Simtabi\Laranail\Toolkit\Services\CacheService;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Clean façade over the rinvex/countries data package and a slim Laravel-locale
 * registry. Read-only and deterministic — the package ships static JSON, so the
 * expensive list builds (country summaries, currencies, timezones) are cached.
 *
 * Geographic grouping (continent / region / subregion) is DERIVED from the
 * data package's long-list `geo` block; nothing is hand-mapped here.
 *
 * @phpstan-import-type CountrySummary from AtlasServiceInterface
 * @phpstan-import-type LanguageEntry from AtlasServiceInterface
 */
class AtlasService implements AtlasServiceInterface
{
    /**
     * Allowed `forSelectBox()` label keys — guards array access against
     * arbitrary user-supplied keys.
     *
     * @var array<int, string>
     */
    private const LABEL_KEYS = ['name', 'official_name', 'native_name'];

    /**
     * In-process memo for the language registry (loaded from config).
     *
     * @var array<string, LanguageEntry>|null
     */
    private ?array $languages = null;

    /**
     * In-process memo for the continent display-name map (loaded from config).
     *
     * @var array<string, string>|null
     */
    private ?array $continents = null;

    /**
     * @param CacheService     $cache        Cache wrapper for the expensive list builds.
     * @param ConfigRepository $config       Config repository holding the Atlas config.
     * @param string           $defaultLabel Default `forSelectBox()` label key.
     * @param int              $cacheTtl     Cache TTL in minutes for derived lists.
     */
    public function __construct(
        private readonly CacheService $cache,
        private readonly ConfigRepository $config,
        private readonly string $defaultLabel = 'name',
        private readonly int $cacheTtl = 1440,
    ) {}

    public function countries(): array
    {
        /** @var array<string, CountrySummary> $result */
        $result = $this->cache->remember(
            'laranail.atlas.countries',
            fn (): array => $this->buildCountries(),
            $this->cacheTtl,
        );

        return $result;
    }

    public function country(string $code): ?array
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        $countries = $this->countries();
        $upper = strtoupper($code);

        // ISO2 fast path.
        if (strlen($upper) === 2) {
            return $countries[$upper] ?? null;
        }

        // ISO3 lookup.
        if (strlen($upper) === 3) {
            foreach ($countries as $country) {
                if ($country['iso3'] === $upper) {
                    return $country;
                }
            }
        }

        return null;
    }

    public function forSelectBox(string $label = 'name', bool $iso3 = false): array
    {
        if (!in_array($label, self::LABEL_KEYS, true)) {
            $label = in_array($this->defaultLabel, self::LABEL_KEYS, true) ? $this->defaultLabel : 'name';
        }

        /** @var 'name'|'official_name'|'native_name' $label */
        $map = [];

        foreach ($this->countries() as $country) {
            $key = $iso3 ? $country['iso3'] : $country['iso2'];
            $map[$key] = $country[$label];
        }

        asort($map);

        return $map;
    }

    public function currencies(): array
    {
        /** @var array<int, string> $result */
        $result = $this->cache->remember(
            'laranail.atlas.currencies',
            static function (): array {
                /** @var array<string, mixed> $raw */
                $raw = CurrencyLoader::currencies();
                $codes = array_filter(
                    array_map(
                        static fn (mixed $code): string => Cast::toString($code),
                        array_keys($raw),
                    ),
                    static fn (string $code): bool => $code !== '',
                );
                $codes = array_values(array_unique($codes));
                sort($codes);

                return $codes;
            },
            $this->cacheTtl,
        );

        return $result;
    }

    public function timezones(): array
    {
        /** @var array<int, string> $result */
        $result = $this->cache->remember(
            'laranail.atlas.timezones',
            static function (): array {
                $zones = DateTimeZone::listIdentifiers();
                sort($zones);

                return $zones;
            },
            $this->cacheTtl,
        );

        return $result;
    }

    public function continents(): array
    {
        return $this->loadContinents();
    }

    public function countriesByContinent(): array
    {
        /** @var array<string, list<CountrySummary>> $result */
        $result = $this->cache->remember(
            'laranail.atlas.countries_by_continent',
            function (): array {
                $grouped = [];

                // Seed every configured continent so empty groups still appear.
                foreach (array_keys($this->loadContinents()) as $code) {
                    $grouped[$code] = [];
                }

                foreach ($this->countries() as $country) {
                    $code = $country['continent'];

                    if ($code === '') {
                        continue;
                    }

                    $grouped[$code][] = $country;
                }

                return $grouped;
            },
            $this->cacheTtl,
        );

        return $result;
    }

    public function countriesInContinent(string $continent): array
    {
        $code = $this->resolveContinentCode($continent);

        if ($code === null) {
            return [];
        }

        return $this->countriesByContinent()[$code] ?? [];
    }

    public function continentForCountry(string $code): ?string
    {
        $country = $this->country($code);

        if ($country === null || $country['continent'] === '') {
            return null;
        }

        return $country['continent'];
    }

    public function regions(): array
    {
        /** @var array<int, string> $result */
        $result = $this->cache->remember(
            'laranail.atlas.regions',
            function (): array {
                $regions = [];

                foreach ($this->countries() as $country) {
                    if ($country['region'] !== '') {
                        $regions[$country['region']] = true;
                    }
                }

                $list = array_keys($regions);
                sort($list);

                return $list;
            },
            $this->cacheTtl,
        );

        return $result;
    }

    public function subregions(): array
    {
        /** @var array<int, string> $result */
        $result = $this->cache->remember(
            'laranail.atlas.subregions',
            function (): array {
                $subregions = [];

                foreach ($this->countries() as $country) {
                    if ($country['subregion'] !== '') {
                        $subregions[$country['subregion']] = true;
                    }
                }

                $list = array_keys($subregions);
                sort($list);

                return $list;
            },
            $this->cacheTtl,
        );

        return $result;
    }

    public function languages(): array
    {
        return $this->loadLanguages();
    }

    public function locales(): array
    {
        return array_keys($this->loadLanguages());
    }

    public function availableLocales(): array
    {
        $base = function_exists('resource_path') ? resource_path('lang') : '';

        if ($base === '' || !is_dir($base)) {
            return [];
        }

        $dirs = $this->scanLocaleDirs($base);
        $languages = $this->loadLanguages();
        $available = [];

        foreach ($dirs as $locale) {
            $normalised = str_replace('-', '_', $locale);

            $entry = $languages[$locale]
                ?? $languages[$normalised]
                ?? $this->matchByIso639($languages, $locale, $normalised);

            if ($entry === null) {
                continue;
            }

            $available[$locale] = [
                'locale' => $locale,
                'name' => $entry['native_name'],
                'flag' => $entry['flag'],
            ];
        }

        return $available;
    }

    /**
     * Build the compact country summaries keyed by upper-case ISO2.
     *
     * The flat scalar fields come from the data package's short-list; the
     * geographic block (continent / region / subregion) is merged in from the
     * long-list, keyed by ISO2.
     *
     * @return array<string, CountrySummary>
     */
    private function buildCountries(): array
    {
        /** @var array<string, array<string, mixed>> $raw */
        $raw = CountryLoader::countries();
        $geo = $this->buildGeoIndex();
        $countries = [];

        foreach ($raw as $entry) {
            $iso2 = strtoupper(Cast::toString($entry['iso_3166_1_alpha2'] ?? ''));
            $iso3 = strtoupper(Cast::toString($entry['iso_3166_1_alpha3'] ?? ''));

            if ($iso2 === '') {
                continue;
            }

            $place = $geo[$iso2] ?? ['continent' => '', 'continent_name' => '', 'region' => '', 'subregion' => ''];

            $countries[$iso2] = [
                'name' => Cast::toString($entry['name'] ?? ''),
                'official_name' => Cast::toString($entry['official_name'] ?? ($entry['name'] ?? '')),
                'native_name' => Cast::toString($entry['native_name'] ?? ($entry['name'] ?? '')),
                'iso2' => $iso2,
                'iso3' => $iso3,
                'currency' => Cast::toString($entry['currency'] ?? ''),
                'calling_code' => Cast::toString($entry['calling_code'] ?? ''),
                'emoji' => Cast::toString($entry['emoji'] ?? ''),
                'continent' => $place['continent'],
                'continent_name' => $place['continent_name'],
                'region' => $place['region'],
                'subregion' => $place['subregion'],
            ];
        }

        ksort($countries);

        return $countries;
    }

    /**
     * Build an ISO2 => {continent, continent_name, region, subregion} index
     * from the data package's long-list `geo` block.
     *
     * rinvex stores `geo.continent` as a single `{CODE: Name}` map (e.g.
     * `{"AF": "Africa"}`), with `geo.region` / `geo.subregion` as plain
     * strings. We read the first (and only) continent key/value pair.
     *
     * @return array<string, array{continent: string, continent_name: string, region: string, subregion: string}>
     */
    private function buildGeoIndex(): array
    {
        /** @var array<string, array<string, mixed>> $long */
        $long = CountryLoader::countries(longlist: true);
        $index = [];

        foreach ($long as $entry) {
            $iso2 = strtoupper(Cast::toString($entry['iso_3166_1_alpha2'] ?? ''));

            if ($iso2 === '') {
                continue;
            }

            /** @var array<string, mixed> $geoBlock */
            $geoBlock = is_array($entry['geo'] ?? null) ? $entry['geo'] : [];

            [$continentCode, $continentName] = $this->firstContinent($geoBlock['continent'] ?? null);

            $index[$iso2] = [
                'continent' => $continentCode,
                'continent_name' => $continentName,
                'region' => Cast::toString($geoBlock['region'] ?? ''),
                'subregion' => Cast::toString($geoBlock['subregion'] ?? ''),
            ];
        }

        return $index;
    }

    /**
     * Extract the `[code, name]` pair from rinvex's `{CODE: Name}` continent
     * map, falling back to empty strings when absent or malformed.
     *
     * @return array{0: string, 1: string}
     */
    private function firstContinent(mixed $continent): array
    {
        if (!is_array($continent) || $continent === []) {
            return ['', ''];
        }

        $code = array_key_first($continent);
        $name = $continent[$code] ?? '';

        return [Cast::toString($code), Cast::toString($name)];
    }

    /**
     * Resolve a continent code or English name (case-insensitive) to its
     * canonical continent code, or null when nothing matches.
     */
    private function resolveContinentCode(string $continent): ?string
    {
        $continent = trim($continent);

        if ($continent === '') {
            return null;
        }

        $continents = $this->loadContinents();
        $upper = strtoupper($continent);

        // Code fast path (e.g. "af" / "AF").
        if (isset($continents[$upper])) {
            return $upper;
        }

        // Name lookup (e.g. "europe" / "North America").
        foreach ($continents as $code => $name) {
            if (strcasecmp($name, $continent) === 0) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Load (and memo) the continent display-name map from config.
     *
     * @return array<string, string>
     */
    private function loadContinents(): array
    {
        if ($this->continents !== null) {
            return $this->continents;
        }

        /** @var array<string, mixed> $raw */
        $raw = (array) $this->config->get('laranail-toolkit-atlas.continents', []);
        $continents = [];

        foreach ($raw as $code => $name) {
            $continents[strtoupper(Cast::toString($code))] = Cast::toString($name);
        }

        return $this->continents = $continents;
    }

    /**
     * Load (and memo) the language registry from config.
     *
     * @return array<string, LanguageEntry>
     */
    private function loadLanguages(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }

        /** @var array<string, LanguageEntry> $data */
        $data = (array) $this->config->get('laranail-toolkit-atlas.languages', []);

        return $this->languages = $data;
    }

    /**
     * Find a language entry whose ISO 639-1 code matches the locale (legacy
     * fallback: a bare `en` directory maps to the first `en_*` registry entry).
     *
     * @param array<string, LanguageEntry> $languages
     *
     * @return LanguageEntry|null
     */
    private function matchByIso639(array $languages, string $locale, string $normalised): ?array
    {
        foreach ($languages as $entry) {
            if ($entry['iso639_1'] === $locale || $entry['iso639_1'] === $normalised) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * List the immediate sub-directory names under a locale base path, skipping
     * the reserved `vendor` directory.
     *
     * @return array<int, string>
     */
    private function scanLocaleDirs(string $base): array
    {
        $dirs = [];

        foreach ((array) scandir($base) as $name) {
            if (!is_string($name) || $name === '.' || $name === '..' || $name === 'vendor') {
                continue;
            }

            if (is_dir($base . DIRECTORY_SEPARATOR . $name)) {
                $dirs[] = $name;
            }
        }

        return $dirs;
    }
}
