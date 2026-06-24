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

    public function test_languages_and_locales_have_a_known_entry(): void
    {
        $languages = $this->service()->languages();

        $this->assertArrayHasKey('en_US', $languages);
        $this->assertSame('en', $languages['en_US']['iso639_1']);
        $this->assertSame('English', $languages['en_US']['native_name']);
        $this->assertSame('ltr', $languages['en_US']['dir']);

        $this->assertContains('en_US', $this->service()->locales());
        $this->assertContains('ar', $this->service()->locales());
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
