<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Modules\Atlas;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Atlas\Atlas;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasService;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('atlas')]
class AtlasServiceTest extends TestCase
{
    private function service(): AtlasService
    {
        $service = $this->app->make(AtlasServiceInterface::class);
        $this->assertInstanceOf(AtlasService::class, $service);

        return $service;
    }

    public function test_it_binds_the_interface_and_alias(): void
    {
        $this->assertInstanceOf(AtlasService::class, $this->app->make(AtlasServiceInterface::class));
        $this->assertInstanceOf(AtlasService::class, $this->app->make('laranail.atlas'));
    }

    public function test_countries_is_non_empty_and_has_a_known_entry(): void
    {
        $countries = $this->service()->countries();

        $this->assertNotEmpty($countries);
        $this->assertGreaterThan(200, count($countries));
        $this->assertArrayHasKey('US', $countries);
        $this->assertSame('United States', $countries['US']['name']);
        $this->assertSame('USA', $countries['US']['iso3']);
        $this->assertSame('USD', $countries['US']['currency']);

        // Geographic block is derived from the rinvex long-list `geo`.
        $this->assertSame('NA', $countries['US']['continent']);
        $this->assertSame('North America', $countries['US']['continent_name']);
        $this->assertSame('Americas', $countries['US']['region']);
        $this->assertSame('Northern America', $countries['US']['subregion']);
    }

    public function test_country_resolves_by_iso2_and_iso3_case_insensitively(): void
    {
        $byIso2 = $this->service()->country('US');
        $this->assertNotNull($byIso2);
        $this->assertSame('United States', $byIso2['name']);
        $this->assertSame('US', $byIso2['iso2']);

        $byIso2Lower = $this->service()->country('us');
        $this->assertNotNull($byIso2Lower);
        $this->assertSame('US', $byIso2Lower['iso2']);

        $byIso3 = $this->service()->country('usa');
        $this->assertNotNull($byIso3);
        $this->assertSame('US', $byIso3['iso2']);
        $this->assertSame('USA', $byIso3['iso3']);
    }

    public function test_country_returns_null_for_unknown_or_blank_codes(): void
    {
        $this->assertNull($this->service()->country('ZZ'));
        $this->assertNull($this->service()->country('ZZZ'));
        $this->assertNull($this->service()->country(''));
        $this->assertNull($this->service()->country('   '));
        $this->assertNull($this->service()->country('TOOLONG'));
    }

    public function test_for_select_box_returns_code_to_label_keyed_by_iso2(): void
    {
        $box = $this->service()->forSelectBox();

        $this->assertArrayHasKey('US', $box);
        $this->assertSame('United States', $box['US']);
        // Sorted alphabetically by label.
        $this->assertSame('Afghanistan', reset($box));
    }

    public function test_for_select_box_can_key_by_iso3_and_use_official_name(): void
    {
        $box = $this->service()->forSelectBox('official_name', iso3: true);

        $this->assertArrayHasKey('USA', $box);
        $this->assertSame('United States of America', $box['USA']);
    }

    public function test_for_select_box_falls_back_to_name_for_unknown_label(): void
    {
        $box = $this->service()->forSelectBox('not_a_real_label');

        $this->assertSame('United States', $box['US']);
    }

    public function test_currencies_is_non_empty_and_includes_usd(): void
    {
        $currencies = $this->service()->currencies();

        $this->assertNotEmpty($currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('EUR', $currencies);
        // Sorted + unique.
        $sorted = $currencies;
        sort($sorted);
        $this->assertSame($sorted, $currencies);
        $this->assertSame(array_values(array_unique($currencies)), $currencies);
    }

    public function test_timezones_is_non_empty_and_includes_known_zone(): void
    {
        $timezones = $this->service()->timezones();

        $this->assertNotEmpty($timezones);
        $this->assertContains('Europe/London', $timezones);
        $this->assertContains('UTC', $timezones);
    }

    public function test_continents_has_seven_entries(): void
    {
        $continents = $this->service()->continents();

        $this->assertCount(7, $continents);
        $this->assertSame('Africa', $continents['AF']);
        $this->assertSame('Europe', $continents['EU']);
        $this->assertSame('Asia', $continents['AS']);
        $this->assertSame('North America', $continents['NA']);
        $this->assertSame('South America', $continents['SA']);
        $this->assertSame('Oceania', $continents['OC']);
        $this->assertSame('Antarctica', $continents['AN']);
    }

    public function test_countries_by_continent_groups_every_continent(): void
    {
        $grouped = $this->service()->countriesByContinent();

        // All seven continent codes are present as keys (even if empty).
        $this->assertSame(
            ['AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA'],
            array_keys($grouped),
        );

        $iso2 = static fn (array $list): array => array_map(
            static fn (array $country): string => $country['iso2'],
            $list,
        );

        $this->assertContains('KE', $iso2($grouped['AF']));
        $this->assertContains('FR', $iso2($grouped['EU']));
        $this->assertContains('JP', $iso2($grouped['AS']));
        $this->assertContains('US', $iso2($grouped['NA']));
    }

    public function test_countries_in_continent_accepts_code_or_name_case_insensitively(): void
    {
        $service = $this->service();

        $byCode = $service->countriesInContinent('EU');
        $byName = $service->countriesInContinent('Europe');
        $byMixedCaseName = $service->countriesInContinent('europe');
        $byLowerCode = $service->countriesInContinent('eu');

        $this->assertSame($byCode, $byName);
        $this->assertSame($byCode, $byMixedCaseName);
        $this->assertSame($byCode, $byLowerCode);
        $this->assertNotEmpty($byCode);

        $iso2 = array_map(static fn (array $c): string => $c['iso2'], $byCode);
        $this->assertContains('FR', $iso2);
        $this->assertNotContains('KE', $iso2);

        // Unknown continent yields an empty list.
        $this->assertSame([], $service->countriesInContinent('Atlantis'));
        $this->assertSame([], $service->countriesInContinent(''));
    }

    public function test_continent_for_country_resolves_by_iso2_and_iso3(): void
    {
        $service = $this->service();

        $this->assertSame('AF', $service->continentForCountry('KE'));
        $this->assertSame('AF', $service->continentForCountry('ken'));
        $this->assertSame('EU', $service->continentForCountry('FR'));
        $this->assertSame('AS', $service->continentForCountry('JP'));
        $this->assertSame('NA', $service->continentForCountry('US'));

        $this->assertNull($service->continentForCountry('ZZ'));
        $this->assertNull($service->continentForCountry(''));
    }

    public function test_regions_and_subregions_are_sorted_and_non_empty(): void
    {
        $service = $this->service();

        $regions = $service->regions();
        $this->assertNotEmpty($regions);
        $this->assertContains('Africa', $regions);
        $this->assertContains('Europe', $regions);
        $sorted = $regions;
        sort($sorted);
        $this->assertSame($sorted, $regions);

        $subregions = $service->subregions();
        $this->assertNotEmpty($subregions);
        $this->assertContains('Eastern Africa', $subregions);
        $this->assertContains('Western Europe', $subregions);
        $sortedSubs = $subregions;
        sort($sortedSubs);
        $this->assertSame($sortedSubs, $subregions);
    }

    public function test_languages_and_locales_have_a_known_entry(): void
    {
        $service = $this->service();
        $languages = $service->languages();

        // Full registry is read from `laranail.toolkit.atlas.languages` config.
        $this->assertCount(89, $languages);

        $this->assertArrayHasKey('en_US', $languages);
        $this->assertSame('en', $languages['en_US']['iso639_1']);
        $this->assertSame('English', $languages['en_US']['native_name']);
        $this->assertSame('ltr', $languages['en_US']['dir']);
        $this->assertSame('us', $languages['en_US']['flag']);

        // A couple of known RTL / endonym entries stay byte-identical.
        $this->assertSame('العربية', $languages['ar']['native_name']);
        $this->assertSame('rtl', $languages['ar']['dir']);
        $this->assertSame('中文 (台灣)', $languages['zh_TW']['native_name']);

        $locales = $service->locales();
        $this->assertCount(89, $locales);
        $this->assertSame(array_keys($languages), $locales);
        $this->assertContains('en_US', $locales);
        $this->assertContains('ar', $locales);
    }

    public function test_results_are_cached_between_calls(): void
    {
        $service = $this->service();

        $first = $service->countries();
        $second = $service->countries();

        $this->assertSame($first, $second);
    }

    public function test_facade_exposes_the_service(): void
    {
        $this->assertSame('United States', Atlas::country('US')['name']);
        $this->assertNotEmpty(Atlas::currencies());
    }
}
