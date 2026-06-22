<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Support\Carbon;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Behaviour coverage for the Carbon macro group: the date/quarter/business-day
 * helpers plus a representative key date for every national holiday calendar.
 */
class CarbonMacroBehaviorTest extends TestCase
{
    // ---- General-purpose date helpers --------------------------------------

    public function test_get_quarter_helper(): void
    {
        $this->assertSame(2, Carbon::parse('2024-05-15')->getQuarter());
        $this->assertSame(1, Carbon::parse('2024-01-01')->getQuarter());
        $this->assertSame(4, Carbon::parse('2024-12-31')->getQuarter());
    }

    public function test_business_day_helpers(): void
    {
        // 2024-06-21 is a Friday; addBusinessDays does not mutate the receiver.
        $friday = Carbon::parse('2024-06-21');
        // 3 business days after Friday: Mon, Tue, Wed -> 2024-06-26.
        $this->assertSame('2024-06-26', $friday->addBusinessDays(3)->toDateString());
        $this->assertSame('2024-06-21', $friday->toDateString(), 'addBusinessDays must not mutate the receiver.');
        $this->assertSame('2024-06-21', Carbon::parse('2024-06-26')->subBusinessDays(3)->toDateString());
    }

    public function test_month_boundary_and_localstring_helpers(): void
    {
        $this->assertTrue(Carbon::parse('2024-02-29')->isLastDayOfMonth());
        $this->assertFalse(Carbon::parse('2024-02-28')->isLastDayOfMonth());
        $this->assertTrue(Carbon::parse('2024-02-01')->isFirstDayOfMonth());

        // fromDateTimeLocalString parses the HTML datetime-local shape back to a Carbon.
        $parsed = Carbon::fromDateTimeLocalString('2024-05-15T13:30');
        $this->assertSame('2024-05-15 13:30:00', $parsed->toDateTimeString());
        $this->assertNull(Carbon::fromDateTimeLocalString('not-a-date'));
    }

    public function test_human_readable_string(): void
    {
        $this->assertSame('Today at 9:30 AM', Carbon::today()->setTime(9, 30)->toHumanReadableString());
        $this->assertStringContainsString('2019', Carbon::parse('2019-03-04 10:00')->toHumanReadableString());
    }

    // ---- Multi-national (Easter-derived + fixed feasts) --------------------

    public function test_multi_national_dates(): void
    {
        $this->assertTrue(Carbon::parse('2024-01-01')->isNewYearsDay());
        $this->assertTrue(Carbon::parse('2024-12-31')->isNewYearsEve());
        $this->assertTrue(Carbon::parse('2024-12-25')->isChristmasDay());
        $this->assertTrue(Carbon::parse('2024-11-01')->isAllSaintsDay());

        // Easter Sunday 2024 = March 31; Good Friday 2024 = March 29.
        $this->assertTrue(Carbon::parse('2024-03-31')->isEasterSunday());
        $this->assertFalse(Carbon::parse('2024-04-01')->isEasterSunday());
        $this->assertTrue(Carbon::parse('2024-03-29')->isGoodFriday());
    }

    public function test_brazilian_holidays_with_fixed_assignment_bug(): void
    {
        $this->assertTrue(Carbon::parse('2024-04-21')->isTiradentesDay());
        $this->assertTrue(Carbon::parse('2024-05-01')->isBrazilianLaborDay());
        $this->assertTrue(Carbon::parse('2024-09-07')->isBrazilianIndependenceDay());
        $this->assertTrue(Carbon::parse('2024-10-12')->isTheDayOfOurLadyAparecida());

        // These two carried the legacy `$this->month = 11` assignment bug, which
        // always evaluated truthy. After the fix they must be date-accurate.
        $this->assertTrue(Carbon::parse('2024-11-02')->isBrazilianDayOfTheDead());
        $this->assertFalse(Carbon::parse('2024-10-02')->isBrazilianDayOfTheDead());
        $this->assertFalse(Carbon::parse('2024-11-03')->isBrazilianDayOfTheDead());

        $this->assertTrue(Carbon::parse('2024-11-15')->isBrazilianRepublicProclamationDay());
        $this->assertFalse(Carbon::parse('2024-10-15')->isBrazilianRepublicProclamationDay());
        $this->assertFalse(Carbon::parse('2024-11-14')->isBrazilianRepublicProclamationDay());
    }

    public function test_canadian_dates(): void
    {
        $this->assertTrue(Carbon::parse('2024-07-01')->isCanadaDay());
        $this->assertTrue(Carbon::parse('2024-12-26')->isBoxingDay());
        // Labour Day 2024 = first Monday of September = Sep 2.
        $this->assertTrue(Carbon::parse('2024-09-02')->isLabourDay());
        $this->assertFalse(Carbon::parse('2024-09-09')->isLabourDay());
        // Canadian Thanksgiving 2024 = second Monday of October = Oct 14.
        $this->assertTrue(Carbon::parse('2024-10-14')->isCanadianThanksgiving());
        // Victoria Day 2024 = Monday before May 25 = May 20.
        $this->assertTrue(Carbon::parse('2024-05-20')->isVictoriaDay());
        // Family Day 2024 = third Monday of February = Feb 19.
        $this->assertTrue(Carbon::parse('2024-02-19')->isFamilyDay());
        $this->assertTrue(Carbon::parse('2024-11-11')->isRemembranceDay());
    }

    public function test_dutch_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-05-05')->isDutchLiberationDay());
        $this->assertTrue(Carbon::parse('2024-05-04')->isDutchRemembranceDay());
        $this->assertTrue(Carbon::parse('2024-12-05')->isSaintNicholasEve());
        // King's Day 2024: April 27 is a Saturday, observed normally.
        $this->assertTrue(Carbon::parse('2024-04-27')->isDutchNationalDay());
        // 2025: April 27 is a Sunday -> observed Saturday April 26 instead.
        $this->assertFalse(Carbon::parse('2025-04-27')->isDutchNationalDay());
        $this->assertTrue(Carbon::parse('2025-04-26')->isDutchNationalDay());
    }

    public function test_french_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-07-14')->isFrenchNationalDay());
        $this->assertTrue(Carbon::parse('2024-08-15')->isAssumptionDay());
        $this->assertTrue(Carbon::parse('2024-11-11')->isFirstWarArmisticeDay());
        $this->assertTrue(Carbon::parse('2024-05-08')->isSecondWarArmisticeDay());
        // Easter Monday 2024 = April 1; Ascension 2024 = May 9; Pentecost = May 19.
        $this->assertTrue(Carbon::parse('2024-04-01')->isEasterMonday());
        $this->assertTrue(Carbon::parse('2024-05-09')->isAscensionDay());
        $this->assertTrue(Carbon::parse('2024-05-19')->isPentecostDay());
    }

    public function test_german_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-05-01')->isGermanLabourDay());
        $this->assertTrue(Carbon::parse('2024-10-03')->isGermanUnityDay());
        // Whit Sunday 2024 = May 19; Whit Monday = May 20; Corpus Christi = May 30.
        $this->assertTrue(Carbon::parse('2024-05-19')->isWhitSunday());
        $this->assertTrue(Carbon::parse('2024-05-19')->isPentecost());
        $this->assertTrue(Carbon::parse('2024-05-20')->isWhitMonday());
        $this->assertTrue(Carbon::parse('2024-05-30')->isCorpusChristi());
        // Heiliger Abend delegates to isChristmasEve (cross-calendar reference).
        $this->assertTrue(Carbon::parse('2024-12-24')->isHeiligerAbend());
        $this->assertTrue(Carbon::parse('2024-12-24')->isHeiligAbend());
    }

    public function test_indian_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-01-26')->isIndianRepublicDay());
        $this->assertFalse(Carbon::parse('1949-01-26')->isIndianRepublicDay());
        $this->assertTrue(Carbon::parse('2024-08-15')->isIndianIndependenceDay());
        $this->assertTrue(Carbon::parse('2024-10-02')->isGandhiJayanti());
    }

    public function test_indonesian_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-08-17')->isIndonesianIndependenceDay());
        $this->assertTrue(Carbon::parse('2024-06-01')->isPancasilaDay());
        $this->assertTrue(Carbon::parse('2024-04-21')->isKartiniDay());
        $this->assertTrue(Carbon::parse('2024-11-10')->isIndonesianHeroesDay());
        $this->assertTrue(Carbon::parse('2024-09-04')->isIndonesiaCustomerDay());
        $this->assertFalse(Carbon::parse('2002-09-04')->isIndonesiaCustomerDay());
    }

    public function test_italian_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-04-25')->isLiberationDay());
        $this->assertTrue(Carbon::parse('2024-06-02')->isRepublicDay());
        $this->assertTrue(Carbon::parse('2024-01-06')->isEpiphany());
        $this->assertTrue(Carbon::parse('2024-12-08')->isImmaculateConceptionFeast());
        $this->assertTrue(Carbon::parse('2024-12-26')->isSaintStephenDay());
        // Workers' Day is May 1 normally, but April 21 in the fascist 1924-1945 window.
        $this->assertTrue(Carbon::parse('2024-05-01')->isWorkersDay());
        $this->assertTrue(Carbon::parse('1930-04-21')->isWorkersDay());
        $this->assertFalse(Carbon::parse('1930-05-01')->isWorkersDay());
    }

    public function test_kenyan_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-12-12')->isKenyanIndependenceDay());
        $this->assertTrue(Carbon::parse('2024-12-12')->isKenyanJamhuriDay());
        $this->assertTrue(Carbon::parse('2024-06-01')->isKenyanMadarakaDay());
        $this->assertTrue(Carbon::parse('2024-10-20')->isKenyanMashujaaDay());
        $this->assertTrue(Carbon::parse('2024-10-10')->isKenyanHudumaDay());
        // Utamaduni Day delegates to isBoxingDay (cross-calendar reference).
        $this->assertTrue(Carbon::parse('2024-12-26')->isKenyanUtamaduniDay());
    }

    public function test_swedish_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-12-24')->isChristmasEve());
        $this->assertTrue(Carbon::parse('2024-06-06')->isSwedishNationalDay());
        // Midsummer Day 2024 = Saturday June 22 (in the 20-26 window).
        $this->assertTrue(Carbon::parse('2024-06-22')->isSwedishMidsummerDay());
        $this->assertFalse(Carbon::parse('2024-06-21')->isSwedishMidsummerDay());
    }

    public function test_ukrainian_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-08-24')->isUkrainianIndependenceDay());
        $this->assertFalse(Carbon::parse('1990-08-24')->isUkrainianIndependenceDay());
        $this->assertTrue(Carbon::parse('2024-10-14')->isUkraineDefenderDay());
        $this->assertTrue(Carbon::parse('2024-06-28')->isUkrainianConstitutionDay());
        $this->assertTrue(Carbon::parse('2024-07-06')->isKupalaNight());
        $this->assertTrue(Carbon::parse('2024-07-07')->isKupalaNight());
        $this->assertTrue(Carbon::parse('2024-05-09')->isVictoryDayOverNazism());
    }

    public function test_us_dates(): void
    {
        $this->assertTrue(Carbon::parse('2024-07-04')->isIndependenceDay());
        $this->assertTrue(Carbon::parse('2024-11-11')->isVeteransDay());
        // MLK Jr Day 2024 = third Monday of January = Jan 15.
        $this->assertTrue(Carbon::parse('2024-01-15')->isMlkJrDay());
        // Presidents Day 2024 = third Monday of February = Feb 19.
        $this->assertTrue(Carbon::parse('2024-02-19')->isPresidentsDay());
        // Memorial Day 2024 = last Monday of May = May 27.
        $this->assertTrue(Carbon::parse('2024-05-27')->isMemorialDay());
        // Columbus Day 2024 = second Monday of October = Oct 14.
        $this->assertTrue(Carbon::parse('2024-10-14')->isColumbusDay());
        // American Thanksgiving 2024 = fourth Thursday of November = Nov 28.
        $this->assertTrue(Carbon::parse('2024-11-28')->isAmericanThanksgiving());
        // Labor Day (US spelling alias) 2024 = first Monday of September = Sep 2.
        $this->assertTrue(Carbon::parse('2024-09-02')->isLaborDay());
    }

    public function test_zambian_holidays(): void
    {
        $this->assertTrue(Carbon::parse('2024-10-24')->isZambianIndependenceDay());
        $this->assertTrue(Carbon::parse('2024-05-01')->isZambianLabourDay());
        $this->assertTrue(Carbon::parse('2024-03-12')->isZambianYouthDay());
        $this->assertTrue(Carbon::parse('2024-05-25')->isZambianAfricanUnityDay());
        $this->assertTrue(Carbon::parse('2024-05-25')->isZambianAfricaDay());
        $this->assertTrue(Carbon::parse('2024-10-18')->isZambianNationalPrayerDay());
        // Heroes' Day 2024 = first Monday of July = Jul 1; Unity Day = the day after.
        $this->assertTrue(Carbon::parse('2024-07-01')->isZambianHeroesDay());
        $this->assertTrue(Carbon::parse('2024-07-02')->isZambianUnityDay());
        // Farmers' Day 2024 = first Monday of August = Aug 5.
        $this->assertTrue(Carbon::parse('2024-08-05')->isZambianFarmersDay());
        // Women's Day: March 8 2024 is a Friday (weekday) -> observed.
        $this->assertTrue(Carbon::parse('2024-03-08')->isZambianWomensDay());
    }
}
