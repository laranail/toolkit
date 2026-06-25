<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Atlas;

use DateTimeZone;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Rinvex\Country\CountryLoader;
use Rinvex\Country\CurrencyLoader;
use Simtabi\Laranail\Toolkit\Support\Cast;
use Simtabi\Laranail\Toolkit\Utilities\CachingUtil;

/**
 * Clean façade over the rinvex/countries data package and a slim Laravel-locale
 * registry. Read-only and deterministic — the package ships static JSON, so the
 * expensive list builds (country summaries, currencies, timezones) are cached.
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
     * @param CachingUtil      $cache        Cache wrapper for the expensive list builds.
     * @param ConfigRepository $config       Config repository holding the language registry.
     * @param string           $defaultLabel Default `forSelectBox()` label key.
     * @param int              $cacheTtl     Cache TTL in minutes for derived lists.
     */
    public function __construct(
        private readonly CachingUtil $cache,
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
     * @return array<string, CountrySummary>
     */
    private function buildCountries(): array
    {
        /** @var array<string, array<string, mixed>> $raw */
        $raw = CountryLoader::countries();
        $countries = [];

        foreach ($raw as $entry) {
            $iso2 = strtoupper(Cast::toString($entry['iso_3166_1_alpha2'] ?? ''));
            $iso3 = strtoupper(Cast::toString($entry['iso_3166_1_alpha3'] ?? ''));

            if ($iso2 === '') {
                continue;
            }

            $countries[$iso2] = [
                'name' => Cast::toString($entry['name'] ?? ''),
                'official_name' => Cast::toString($entry['official_name'] ?? ($entry['name'] ?? '')),
                'native_name' => Cast::toString($entry['native_name'] ?? ($entry['name'] ?? '')),
                'iso2' => $iso2,
                'iso3' => $iso3,
                'currency' => Cast::toString($entry['currency'] ?? ''),
                'calling_code' => Cast::toString($entry['calling_code'] ?? ''),
                'emoji' => Cast::toString($entry['emoji'] ?? ''),
            ];
        }

        ksort($countries);

        return $countries;
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
        $data = (array) $this->config->get('laranail.toolkit.languages', []);

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
