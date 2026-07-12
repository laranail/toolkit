<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Support\Carbon;
use Simtabi\Laranail\Toolkit\Macros\CarbonMacros;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Exhaustive, mutation-hardened coverage for every Carbon macro registered by
 * CarbonMacros.
 *
 * Every assertion pins a fixed reference year (2026 for the "current" cases,
 * the documented floor year for era guards) and asserts the EXACT computed
 * date/value, with neighbouring dates asserted false, so arithmetic, constant,
 * comparison and boolean-logic mutants all die. Easter-relative holidays are
 * cross-checked against multiple independent years so the offset constants are
 * pinned, not merely consistent.
 *
 * Reference dates used throughout (verified against PHP's easter_days() and
 * Carbon's own weekday maths):
 *   Easter Sunday   2026-04-05  (2024-03-31, 2027-03-28)
 *   Good Friday     2026-04-03  Easter Monday 2026-04-06
 *   Ascension (+39) 2026-05-14  Whit/Pentecost Sunday (+49) 2026-05-24
 *   Whit Monday     2026-05-25  Corpus Christi (+60) 2026-06-04
 */
class CarbonMacrosBehaviorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function assertHolds(string $macro, string $date): void
    {
        $this->assertTrue(
            Carbon::parse($date)->{$macro}(),
            sprintf('%s() should hold on %s', $macro, $date),
        );
    }

    private function refuteHolds(string $macro, string $date): void
    {
        $this->assertFalse(
            Carbon::parse($date)->{$macro}(),
            sprintf('%s() should not hold on %s', $macro, $date),
        );
    }

    /**
     * Fixed-date holiday: true on the date, false on the day before/after and
     * on the same day in an adjacent month; optional era floor (true on the
     * floor year, false on the year before it).
     *
     * @param list<string> $offDates
     */
    private function assertFixedDate(string $macro, string $onDate, array $offDates, ?string $floorTrue = null, ?string $floorFalse = null): void
    {
        $this->assertHolds($macro, $onDate);

        foreach ($offDates as $off) {
            $this->refuteHolds($macro, $off);
        }

        if ($floorTrue !== null) {
            $this->assertHolds($macro, $floorTrue);
        }

        if ($floorFalse !== null) {
            $this->refuteHolds($macro, $floorFalse);
        }
    }

    // ---- General-purpose date helpers --------------------------------------

    public function test_get_quarter_for_every_month(): void
    {
        $expected = [1, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 4];

        foreach ($expected as $index => $quarter) {
            $month = $index + 1;
            $this->assertSame(
                $quarter,
                Carbon::create(2026, $month, 15)->getQuarter(),
                "month {$month} should be in quarter {$quarter}",
            );
        }

        // ceil (not floor/round) at the in-quarter boundaries.
        $this->assertSame(1, Carbon::create(2026, 2, 1)->getQuarter());
        $this->assertSame(4, Carbon::create(2026, 11, 30)->getQuarter());
    }

    public function test_add_business_days(): void
    {
        $friday = Carbon::parse('2026-06-26'); // a Friday

        // Zero business days is a no-op (kills the `< -> <=` loop-guard mutant).
        $this->assertSame('2026-06-26', $friday->addBusinessDays(0)->toDateString());

        // One business day after Friday skips the weekend onto Monday.
        $this->assertSame('2026-06-29', $friday->addBusinessDays(1)->toDateString());
        $this->assertSame('2026-06-30', $friday->addBusinessDays(2)->toDateString());
        $this->assertSame('2026-07-01', $friday->addBusinessDays(3)->toDateString());

        // Mid-week add that does not cross a weekend.
        $this->assertSame('2026-06-30', Carbon::parse('2026-06-29')->addBusinessDays(1)->toDateString());

        // Receiver is never mutated.
        $this->assertSame('2026-06-26', $friday->toDateString());
    }

    public function test_sub_business_days(): void
    {
        $monday = Carbon::parse('2026-06-29'); // a Monday

        $this->assertSame('2026-06-29', $monday->subBusinessDays(0)->toDateString());

        // One business day before Monday skips the weekend back onto Friday.
        $this->assertSame('2026-06-26', $monday->subBusinessDays(1)->toDateString());
        $this->assertSame('2026-06-25', $monday->subBusinessDays(2)->toDateString());
        $this->assertSame('2026-06-24', $monday->subBusinessDays(3)->toDateString());

        $this->assertSame('2026-06-29', $monday->toDateString());
    }

    public function test_first_and_last_day_of_month(): void
    {
        $this->assertTrue(Carbon::parse('2026-03-01')->isFirstDayOfMonth());
        $this->assertFalse(Carbon::parse('2026-03-02')->isFirstDayOfMonth());

        // Non-leap February ends on the 28th; leap February on the 29th.
        $this->assertTrue(Carbon::parse('2026-02-28')->isLastDayOfMonth());
        $this->assertFalse(Carbon::parse('2026-02-27')->isLastDayOfMonth());
        $this->assertTrue(Carbon::parse('2024-02-29')->isLastDayOfMonth());
        $this->assertFalse(Carbon::parse('2024-02-28')->isLastDayOfMonth());

        $this->assertTrue(Carbon::parse('2026-01-31')->isLastDayOfMonth());
        $this->assertTrue(Carbon::parse('2026-04-30')->isLastDayOfMonth());
        $this->assertFalse(Carbon::parse('2026-04-29')->isLastDayOfMonth());
    }

    public function test_from_date_time_local_string(): void
    {
        $parsed = Carbon::fromDateTimeLocalString('2026-05-15T13:30');
        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertSame('2026-05-15 13:30:00', $parsed->toDateTimeString());

        $this->assertNull(Carbon::fromDateTimeLocalString('not-a-date'));
        $this->assertNull(Carbon::fromDateTimeLocalString(''));
    }

    public function test_human_readable_string_branches(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-29 12:00'));

        $this->assertSame('Today at 9:30 AM', Carbon::parse('2026-06-29 09:30')->toHumanReadableString());
        $this->assertSame('Yesterday at 9:30 AM', Carbon::parse('2026-06-28 09:30')->toHumanReadableString());
        $this->assertSame('Tomorrow at 9:30 AM', Carbon::parse('2026-06-30 09:30')->toHumanReadableString());

        // Same calendar year, not today/yesterday/tomorrow -> no year shown.
        $this->assertSame('Mar 4 at 10:00 AM', Carbon::parse('2026-03-04 10:00')->toHumanReadableString());

        // Different year -> year shown.
        $this->assertSame('Mar 4, 2019 at 10:00 AM', Carbon::parse('2019-03-04 10:00')->toHumanReadableString());
    }

    // ---- Multi-national (Easter-derived + fixed feasts) --------------------

    public function test_fixed_multi_national_feasts(): void
    {
        $this->assertFixedDate('isNewYearsDay', '2026-01-01', ['2026-01-02', '2026-02-01']);
        $this->assertFixedDate('isAllSaintsDay', '2026-11-01', ['2026-11-02', '2026-10-01']);
        $this->assertFixedDate('isChristmasDay', '2026-12-25', ['2026-12-24', '2026-12-26', '2026-11-25']);
        $this->assertFixedDate('isNewYearsEve', '2026-12-31', ['2026-12-30', '2026-10-31']);
    }

    public function test_easter_sunday_across_multiple_years(): void
    {
        $this->assertHolds('isEasterSunday', '2026-04-05');
        $this->assertHolds('isEasterSunday', '2024-03-31');
        $this->assertHolds('isEasterSunday', '2027-03-28');

        $this->refuteHolds('isEasterSunday', '2026-04-04');
        $this->refuteHolds('isEasterSunday', '2026-04-06');
        $this->refuteHolds('isEasterSunday', '2026-04-12');
        $this->refuteHolds('isEasterSunday', '2024-04-01');
    }

    public function test_easter_relative_offsets(): void
    {
        // Good Friday = Easter - 2.
        $this->assertHolds('isGoodFriday', '2026-04-03');
        $this->assertHolds('isGoodFriday', '2024-03-29');
        $this->refuteHolds('isGoodFriday', '2026-04-02');
        $this->refuteHolds('isGoodFriday', '2026-04-04');

        // Easter Monday = Easter + 1.
        $this->assertHolds('isEasterMonday', '2026-04-06');
        $this->assertHolds('isEasterMonday', '2024-04-01');
        $this->refuteHolds('isEasterMonday', '2026-04-05');
        $this->refuteHolds('isEasterMonday', '2026-04-07');

        // Ascension = Easter + 39 (French and German spellings share the rule).
        foreach (['isAscensionDay', 'isAscensionOfJesus'] as $macro) {
            $this->assertHolds($macro, '2026-05-14');
            $this->assertHolds($macro, '2024-05-09');
            $this->refuteHolds($macro, '2026-05-13');
            $this->refuteHolds($macro, '2026-05-15');
        }

        // Whit/Pentecost Sunday = Easter + 49.
        foreach (['isPentecostDay', 'isWhitSunday', 'isWhitsun', 'isPentecost', 'isPentecostSunday'] as $macro) {
            $this->assertHolds($macro, '2026-05-24');
            $this->refuteHolds($macro, '2026-05-23');
            $this->refuteHolds($macro, '2026-05-25');
        }
        $this->assertHolds('isWhitSunday', '2024-05-19');

        // Whit/Pentecost Monday = Easter + 50.
        foreach (['isWhitMonday', 'isPentecostMonday'] as $macro) {
            $this->assertHolds($macro, '2026-05-25');
            $this->refuteHolds($macro, '2026-05-24');
            $this->refuteHolds($macro, '2026-05-26');
        }

        // Corpus Christi = Easter + 60.
        $this->assertHolds('isCorpusChristi', '2026-06-04');
        $this->assertHolds('isCorpusChristi', '2024-05-30');
        $this->refuteHolds('isCorpusChristi', '2026-06-03');
        $this->refuteHolds('isCorpusChristi', '2026-06-05');
    }

    // ---- Brazilian ---------------------------------------------------------

    public function test_brazilian_holidays(): void
    {
        $this->assertFixedDate('isTiradentesDay', '2026-04-21', ['2026-04-20', '2026-04-22', '2026-03-21']);
        $this->assertFixedDate('isBrazilianLaborDay', '2026-05-01', ['2026-05-02', '2026-04-01']);
        $this->assertFixedDate('isBrazilianIndependenceDay', '2026-09-07', ['2026-09-06', '2026-09-08', '2026-08-07']);
        $this->assertFixedDate('isTheDayOfOurLadyAparecida', '2026-10-12', ['2026-10-11', '2026-10-13', '2026-09-12']);

        // These two carried the legacy `$this->month = 11` assignment bug.
        $this->assertFixedDate('isBrazilianDayOfTheDead', '2026-11-02', ['2026-11-01', '2026-11-03', '2026-10-02', '2026-12-02']);
        $this->assertFixedDate('isBrazilianRepublicProclamationDay', '2026-11-15', ['2026-11-14', '2026-11-16', '2026-10-15', '2026-12-15']);
    }

    // ---- Canadian ----------------------------------------------------------

    public function test_canada_day_and_boxing_day(): void
    {
        $this->assertFixedDate('isCanadaDay', '2026-07-01', ['2026-07-02', '2026-06-01', '2026-08-01']);
        $this->assertFixedDate('isBoxingDay', '2026-12-26', ['2026-12-25', '2026-12-27', '2026-11-26']);
    }

    public function test_remembrance_day_with_floor(): void
    {
        $this->assertFixedDate(
            'isRemembranceDay',
            '2026-11-11',
            ['2026-11-10', '2026-11-12', '2026-10-11'],
            '1931-11-11',
            '1930-11-11',
        );
    }

    public function test_victoria_day(): void
    {
        // Monday before May 25; in 2026 May 25 is itself a Monday -> May 18.
        $this->assertHolds('isVictoriaDay', '2026-05-18');
        $this->refuteHolds('isVictoriaDay', '2026-05-17');
        $this->refuteHolds('isVictoriaDay', '2026-05-19');
        $this->refuteHolds('isVictoriaDay', '2026-05-25'); // day >= 25 guard
        $this->refuteHolds('isVictoriaDay', '2026-05-11'); // a Monday, but not the right one
        $this->refuteHolds('isVictoriaDay', '2026-06-15'); // wrong month
    }

    public function test_canadian_labour_day(): void
    {
        // First Monday of September; 2026 -> Sep 7.
        $this->assertHolds('isLabourDay', '2026-09-07');
        $this->refuteHolds('isLabourDay', '2026-09-08');
        $this->refuteHolds('isLabourDay', '2026-09-14'); // second Monday
        $this->refuteHolds('isLabourDay', '2026-08-07'); // wrong month
        $this->assertHolds('isLabourDay', '1894-09-03'); // floor year (first observed)
        $this->refuteHolds('isLabourDay', '1893-09-04'); // year before the floor
    }

    public function test_canadian_thanksgiving(): void
    {
        // Second Monday of October; 2026 -> Oct 12.
        $this->assertHolds('isCanadianThanksgiving', '2026-10-12');
        $this->refuteHolds('isCanadianThanksgiving', '2026-10-05'); // first Monday
        $this->refuteHolds('isCanadianThanksgiving', '2026-10-19'); // third Monday
        $this->refuteHolds('isCanadianThanksgiving', '2026-09-12'); // wrong month
        $this->assertHolds('isCanadianThanksgiving', '1957-10-14'); // floor year
        $this->refuteHolds('isCanadianThanksgiving', '1956-10-08'); // year before the floor
    }

    public function test_civic_holiday(): void
    {
        // First Monday of August; no era floor.
        $this->assertHolds('isCivicHoliday', '2026-08-03');
        $this->refuteHolds('isCivicHoliday', '2026-08-10'); // second Monday
        $this->refuteHolds('isCivicHoliday', '2026-08-04');
        $this->refuteHolds('isCivicHoliday', '2026-07-06'); // wrong month, a Monday
    }

    public function test_family_day(): void
    {
        // Third Monday of February; 2026 -> Feb 16.
        $this->assertHolds('isFamilyDay', '2026-02-16');
        $this->refuteHolds('isFamilyDay', '2026-02-09'); // second Monday
        $this->refuteHolds('isFamilyDay', '2026-02-23'); // fourth Monday
        $this->refuteHolds('isFamilyDay', '2026-03-16'); // wrong month
        $this->assertHolds('isFamilyDay', '1990-02-19'); // floor year
        $this->refuteHolds('isFamilyDay', '1989-02-20'); // year before the floor
    }

    // ---- Dutch -------------------------------------------------------------

    public function test_dutch_fixed_holidays(): void
    {
        $this->assertFixedDate('isDutchLiberationDay', '2026-05-05', ['2026-05-06', '2026-04-05'], '1945-05-05', '1944-05-05');
        $this->assertFixedDate('isDutchRemembranceDay', '2026-05-04', ['2026-05-03', '2026-04-04'], '1945-05-04', '1944-05-04');
        $this->assertFixedDate('isSaintNicholasEve', '2026-12-05', ['2026-12-04', '2026-12-06', '2026-11-05']);
    }

    public function test_dutch_national_day_rules(): void
    {
        // 2026: April 27 is a Monday -> observed on the 27th.
        $this->assertHolds('isDutchNationalDay', '2026-04-27');
        $this->refuteHolds('isDutchNationalDay', '2026-04-26'); // Sunday -> not observed

        // 2025: April 27 is a Sunday -> moved one day earlier to Saturday the 26th.
        $this->refuteHolds('isDutchNationalDay', '2025-04-27');
        $this->assertHolds('isDutchNationalDay', '2025-04-26');

        // 2024: April 27 is a Saturday -> observed normally.
        $this->assertHolds('isDutchNationalDay', '2024-04-27');

        // Before 2014 it was Queen's Day on April 30th.
        $this->assertHolds('isDutchNationalDay', '2013-04-30');
        $this->refuteHolds('isDutchNationalDay', '2013-04-27');

        // Era floor (1949).
        $this->assertHolds('isDutchNationalDay', '1949-04-30');
        $this->refuteHolds('isDutchNationalDay', '1948-04-30');
    }

    // ---- French ------------------------------------------------------------

    public function test_french_fixed_holidays(): void
    {
        $this->assertFixedDate('isAssumptionDay', '2026-08-15', ['2026-08-14', '2026-08-16', '2026-07-15']);
        $this->assertFixedDate('isFirstWarArmisticeDay', '2026-11-11', ['2026-11-10', '2026-11-12', '2026-10-11'], '1918-11-11', '1917-11-11');
        $this->assertFixedDate('isFrenchNationalDay', '2026-07-14', ['2026-07-13', '2026-07-15', '2026-06-14'], '1880-07-14', '1879-07-14');
        $this->assertFixedDate('isSecondWarArmisticeDay', '2026-05-08', ['2026-05-07', '2026-05-09', '2026-04-08'], '1945-05-08', '1944-05-08');
    }

    // ---- German ------------------------------------------------------------

    public function test_german_fixed_holidays(): void
    {
        $this->assertFixedDate('isGermanLabourDay', '2026-05-01', ['2026-05-02', '2026-04-01']);
        $this->assertFixedDate('isGermanUnityDay', '2026-10-03', ['2026-10-02', '2026-10-04', '2026-09-03'], '1990-10-03', '1989-10-03');

        // Heiliger Abend / Heilig Abend both delegate to Christmas Eve.
        $this->assertFixedDate('isHeiligerAbend', '2026-12-24', ['2026-12-23', '2026-12-25', '2026-11-24']);
        $this->assertFixedDate('isHeiligAbend', '2026-12-24', ['2026-12-23', '2026-12-25', '2026-11-24']);
    }

    // ---- Indian ------------------------------------------------------------

    public function test_indian_holidays(): void
    {
        $this->assertFixedDate('isIndianRepublicDay', '2026-01-26', ['2026-01-25', '2026-01-27', '2026-02-26'], '1950-01-26', '1949-01-26');
        $this->assertFixedDate('isIndianIndependenceDay', '2026-08-15', ['2026-08-14', '2026-08-16', '2026-07-15'], '1947-08-15', '1946-08-15');
        $this->assertFixedDate('isGandhiJayanti', '2026-10-02', ['2026-10-01', '2026-10-03', '2026-09-02'], '1869-10-02', '1868-10-02');
    }

    // ---- Indonesian --------------------------------------------------------

    public function test_indonesian_holidays(): void
    {
        $this->assertFixedDate('isIndonesianIndependenceDay', '2026-08-17', ['2026-08-16', '2026-08-18', '2026-07-17']);
        $this->assertFixedDate('isPancasilaDay', '2026-06-01', ['2026-06-02', '2026-05-01', '2026-07-01']);
        $this->assertFixedDate('isIndonesianLaborDay', '2026-05-01', ['2026-05-02', '2026-04-01']);
        $this->assertFixedDate('isKartiniDay', '2026-04-21', ['2026-04-20', '2026-04-22', '2026-03-21']);
        $this->assertFixedDate('isIndonesianEducationDay', '2026-05-02', ['2026-05-01', '2026-05-03', '2026-04-02']);
        $this->assertFixedDate('isIndonesiaCustomerDay', '2026-09-04', ['2026-09-03', '2026-09-05', '2026-08-04'], '2003-09-04', '2002-09-04');
        $this->assertFixedDate('isIndonesianHeroesDay', '2026-11-10', ['2026-11-09', '2026-11-11', '2026-10-10']);
        $this->assertFixedDate('isIndonesianMothersDay', '2026-12-22', ['2026-12-21', '2026-12-23', '2026-11-22']);
    }

    // ---- Italian -----------------------------------------------------------

    public function test_italian_fixed_holidays(): void
    {
        $this->assertFixedDate('isLiberationDay', '2026-04-25', ['2026-04-24', '2026-04-26', '2026-03-25'], '1946-04-25', '1945-04-25');
        $this->assertFixedDate('isRepublicDay', '2026-06-02', ['2026-06-01', '2026-06-03', '2026-05-02'], '1946-06-02', '1945-06-02');
        $this->assertFixedDate('isImmaculateConceptionFeast', '2026-12-08', ['2026-12-07', '2026-12-09', '2026-11-08']);
        $this->assertFixedDate('isAssumptionOfMaryFeast', '2026-08-15', ['2026-08-14', '2026-08-16', '2026-07-15']);
        $this->assertFixedDate('isEpiphany', '2026-01-06', ['2026-01-05', '2026-01-07', '2026-02-06']);
        $this->assertFixedDate('isSaintStephenDay', '2026-12-26', ['2026-12-25', '2026-12-27', '2026-11-26']);
        $this->assertFixedDate('isSaintSylvesterDay', '2026-12-31', ['2026-12-30', '2026-10-31']);
    }

    public function test_italian_workers_day_with_fascist_window(): void
    {
        // Normal rule: May 1st, from 1890.
        $this->assertHolds('isWorkersDay', '2026-05-01');
        $this->refuteHolds('isWorkersDay', '2026-04-21');
        $this->assertHolds('isWorkersDay', '1890-05-01'); // era floor
        $this->refuteHolds('isWorkersDay', '1889-05-01'); // before the floor

        // 1924-1945 inclusive: moved to April 21st.
        $this->assertHolds('isWorkersDay', '1924-04-21'); // window start
        $this->assertHolds('isWorkersDay', '1930-04-21');
        $this->assertHolds('isWorkersDay', '1945-04-21'); // window end
        $this->refuteHolds('isWorkersDay', '1930-05-01'); // May 1 not observed inside the window

        // Years bracketing the window fall back to May 1st.
        $this->assertHolds('isWorkersDay', '1923-05-01');
        $this->refuteHolds('isWorkersDay', '1923-04-21');
        $this->assertHolds('isWorkersDay', '1946-05-01');
    }

    // ---- Kenyan ------------------------------------------------------------

    public function test_kenyan_holidays(): void
    {
        $this->assertFixedDate('isKenyanIndependenceDay', '2026-12-12', ['2026-12-11', '2026-12-13', '2026-11-12'], '1963-12-12', '1962-12-12');
        // Jamhuri Day is an alias of Independence Day.
        $this->assertFixedDate('isKenyanJamhuriDay', '2026-12-12', ['2026-12-11', '2026-12-13', '2026-11-12'], '1963-12-12', '1962-12-12');
        $this->assertFixedDate('isKenyanLabourDay', '2026-05-01', ['2026-05-02', '2026-04-01']);
        $this->assertFixedDate('isKenyanMadarakaDay', '2026-06-01', ['2026-06-02', '2026-05-01'], '1920-06-01', '1919-06-01');
        $this->assertFixedDate('isKenyanHudumaDay', '2026-10-10', ['2026-10-09', '2026-10-11', '2026-09-10']);
        $this->assertFixedDate('isKenyanMashujaaDay', '2026-10-20', ['2026-10-19', '2026-10-21', '2026-11-20']);
        // Utamaduni Day is an alias of Boxing Day.
        $this->assertFixedDate('isKenyanUtamaduniDay', '2026-12-26', ['2026-12-25', '2026-12-27', '2026-11-26']);
    }

    // ---- Swedish -----------------------------------------------------------

    public function test_swedish_holidays(): void
    {
        $this->assertFixedDate('isChristmasEve', '2026-12-24', ['2026-12-23', '2026-12-25', '2026-11-24']);
        $this->assertFixedDate('isSwedishNationalDay', '2026-06-06', ['2026-06-05', '2026-06-07', '2026-05-06']);
    }

    public function test_swedish_midsummer_day_window(): void
    {
        // The Saturday in the June 20-26 window. 2026 -> the 20th; 2027 -> the 26th.
        $this->assertHolds('isSwedishMidsummerDay', '2026-06-20'); // lower bound
        $this->assertHolds('isSwedishMidsummerDay', '2027-06-26'); // upper bound

        $this->refuteHolds('isSwedishMidsummerDay', '2026-06-21'); // Sunday, not Saturday
        $this->refuteHolds('isSwedishMidsummerDay', '2026-06-27'); // Saturday but day > 26
        $this->refuteHolds('isSwedishMidsummerDay', '2026-06-13'); // Saturday but day < 20
        $this->refuteHolds('isSwedishMidsummerDay', '2026-05-23'); // Saturday in range-of-days but wrong month
    }

    // ---- Ukrainian ---------------------------------------------------------

    public function test_ukrainian_holidays(): void
    {
        $this->assertFixedDate('isUkrainianIndependenceDay', '2026-08-24', ['2026-08-23', '2026-08-25', '2026-07-24'], '1991-08-24', '1990-08-24');
        $this->assertFixedDate('isUkraineDefenderDay', '2026-10-14', ['2026-10-13', '2026-10-15', '2026-09-14'], '2015-10-14', '2014-10-14');
        $this->assertFixedDate('isUkrainianConstitutionDay', '2026-06-28', ['2026-06-27', '2026-06-29', '2026-07-28'], '1996-06-28', '1995-06-28');
        $this->assertFixedDate('isUkrainianLabourDay', '2026-05-01', ['2026-05-02', '2026-04-01']);
        $this->assertFixedDate('isVictoryDayOverNazism', '2026-05-09', ['2026-05-08', '2026-05-10', '2026-04-09'], '2015-05-09', '2014-05-09');

        // Kupala Night spans July 6th-7th.
        $this->assertHolds('isKupalaNight', '2026-07-06');
        $this->assertHolds('isKupalaNight', '2026-07-07');
        $this->refuteHolds('isKupalaNight', '2026-07-05');
        $this->refuteHolds('isKupalaNight', '2026-07-08');
        $this->refuteHolds('isKupalaNight', '2026-06-06');
        $this->refuteHolds('isKupalaNight', '2026-06-07');
    }

    // ---- United States -----------------------------------------------------

    public function test_us_fixed_holidays(): void
    {
        $this->assertFixedDate('isIndependenceDay', '2026-07-04', ['2026-07-03', '2026-07-05', '2026-08-04'], '1781-07-04', '1780-07-04');
        $this->assertFixedDate('isVeteransDay', '2026-11-11', ['2026-11-10', '2026-11-12', '2026-10-11'], '1954-11-11', '1953-11-11');
    }

    public function test_mlk_jr_day(): void
    {
        // Third Monday of January; 2026 -> Jan 19.
        $this->assertHolds('isMlkJrDay', '2026-01-19');
        $this->refuteHolds('isMlkJrDay', '2026-01-12'); // second Monday
        $this->refuteHolds('isMlkJrDay', '2026-01-26'); // fourth Monday
        $this->refuteHolds('isMlkJrDay', '2026-02-19'); // wrong month
        $this->assertHolds('isMlkJrDay', '1986-01-20'); // floor year
        $this->refuteHolds('isMlkJrDay', '1985-01-21'); // before the floor
    }

    public function test_presidents_day(): void
    {
        // Third Monday of February; 2026 -> Feb 16.
        $this->assertHolds('isPresidentsDay', '2026-02-16');
        $this->refuteHolds('isPresidentsDay', '2026-02-09');
        $this->refuteHolds('isPresidentsDay', '2026-02-23');
        $this->refuteHolds('isPresidentsDay', '2026-03-16'); // wrong month
        $this->assertHolds('isPresidentsDay', '1880-02-16'); // floor year
        $this->refuteHolds('isPresidentsDay', '1879-02-17'); // before the floor
    }

    public function test_memorial_day(): void
    {
        // Last Monday of May; 2026 -> May 25.
        $this->assertHolds('isMemorialDay', '2026-05-25');
        $this->refuteHolds('isMemorialDay', '2026-05-18'); // second-to-last Monday
        $this->refuteHolds('isMemorialDay', '2026-04-27'); // wrong month
        $this->assertHolds('isMemorialDay', '1874-05-25'); // floor year
        $this->refuteHolds('isMemorialDay', '1873-05-26'); // before the floor
    }

    public function test_columbus_day(): void
    {
        // Second Monday of October; 2026 -> Oct 12.
        $this->assertHolds('isColumbusDay', '2026-10-12');
        $this->refuteHolds('isColumbusDay', '2026-10-05'); // first Monday
        $this->refuteHolds('isColumbusDay', '2026-10-19'); // third Monday
        $this->assertHolds('isColumbusDay', '1869-10-11'); // floor year
        $this->refuteHolds('isColumbusDay', '1868-10-12'); // before the floor
    }

    public function test_american_thanksgiving(): void
    {
        // Fourth Thursday of November; 2026 -> Nov 26.
        $this->assertHolds('isAmericanThanksgiving', '2026-11-26');
        $this->refuteHolds('isAmericanThanksgiving', '2026-11-19'); // third Thursday
        $this->refuteHolds('isAmericanThanksgiving', '2026-11-27');
        $this->assertHolds('isAmericanThanksgiving', '1789-11-26'); // floor year
        $this->refuteHolds('isAmericanThanksgiving', '1788-11-27'); // before the floor
    }

    public function test_us_labor_day_alias(): void
    {
        // US spelling alias for the (Canadian) first-Monday-of-September rule.
        $this->assertHolds('isLaborDay', '2026-09-07');
        $this->refuteHolds('isLaborDay', '2026-09-14');
        $this->refuteHolds('isLaborDay', '2026-08-07');
    }

    // ---- Zambian -----------------------------------------------------------

    public function test_zambian_fixed_holidays(): void
    {
        $this->assertFixedDate('isZambianIndependenceDay', '2026-10-24', ['2026-10-23', '2026-10-25', '2026-09-24'], '1964-10-24', '1963-10-24');
        $this->assertFixedDate('isZambianLabourDay', '2026-05-01', ['2026-05-02', '2026-04-01']);
        $this->assertFixedDate('isZambianYouthDay', '2026-03-12', ['2026-03-11', '2026-03-13', '2026-04-12'], '1966-03-12', '1965-03-12');
        $this->assertFixedDate('isZambianAfricanUnityDay', '2026-05-25', ['2026-05-24', '2026-05-26', '2026-04-25'], '1963-05-25', '1962-05-25');
        // Africa Day is an alias of African Unity Day.
        $this->assertFixedDate('isZambianAfricaDay', '2026-05-25', ['2026-05-24', '2026-05-26', '2026-04-25'], '1963-05-25', '1962-05-25');
        $this->assertFixedDate('isZambianNationalPrayerDay', '2026-10-18', ['2026-10-17', '2026-10-19', '2026-09-18'], '2015-10-18', '2014-10-18');
    }

    public function test_zambian_heroes_unity_and_farmers_days(): void
    {
        // Heroes' Day: first Monday of July; 2026 -> Jul 6.
        $this->assertHolds('isZambianHeroesDay', '2026-07-06');
        $this->refuteHolds('isZambianHeroesDay', '2026-07-13'); // second Monday
        $this->refuteHolds('isZambianHeroesDay', '2026-08-06'); // wrong month
        $this->assertHolds('isZambianHeroesDay', '1964-07-06'); // floor year
        $this->refuteHolds('isZambianHeroesDay', '1963-07-01'); // before the floor

        // Unity Day: the day after Heroes' Day; 2026 -> Jul 7.
        $this->assertHolds('isZambianUnityDay', '2026-07-07');
        $this->refuteHolds('isZambianUnityDay', '2026-07-06');
        $this->refuteHolds('isZambianUnityDay', '2026-07-14');
        $this->refuteHolds('isZambianUnityDay', '2026-08-07'); // wrong month
        $this->assertHolds('isZambianUnityDay', '1964-07-07'); // floor year
        $this->refuteHolds('isZambianUnityDay', '1963-07-02'); // before the floor

        // Farmers' Day: first Monday of August; 2026 -> Aug 3.
        $this->assertHolds('isZambianFarmersDay', '2026-08-03');
        $this->refuteHolds('isZambianFarmersDay', '2026-08-10'); // second Monday
        $this->refuteHolds('isZambianFarmersDay', '2026-07-06'); // wrong month
        $this->assertHolds('isZambianFarmersDay', '1964-08-03'); // floor year
        $this->refuteHolds('isZambianFarmersDay', '1963-08-05'); // before the floor
    }

    public function test_zambian_womens_day_weekend_shift(): void
    {
        // March 8th when it is a weekday.
        $this->assertHolds('isZambianWomensDay', '2023-03-08'); // a Wednesday
        $this->assertHolds('isZambianWomensDay', '2027-03-08'); // a Monday
        $this->assertHolds('isZambianWomensDay', '1977-03-08'); // floor year, a Tuesday

        // March 8th on a weekend shifts to the following Monday (the 9th or 10th).
        $this->refuteHolds('isZambianWomensDay', '2026-03-08'); // a Sunday
        $this->assertHolds('isZambianWomensDay', '2026-03-09'); // the following Monday
        $this->refuteHolds('isZambianWomensDay', '2026-03-10'); // a Tuesday, not shifted to

        $this->refuteHolds('isZambianWomensDay', '2025-03-08'); // a Saturday
        $this->refuteHolds('isZambianWomensDay', '2025-03-09'); // a Sunday
        $this->assertHolds('isZambianWomensDay', '2025-03-10'); // the following Monday

        // Guards: wrong month, and the year before the floor (1976-03-08 is a weekday).
        $this->refuteHolds('isZambianWomensDay', '2026-04-09');
        $this->refuteHolds('isZambianWomensDay', '1976-03-08');
    }

    // ---- nthWeekdayDay helper ---------------------------------------------

    public function test_nth_weekday_day_helper(): void
    {
        $july2026 = Carbon::create(2026, 7, 1);

        // First Monday of July 2026 is the 6th; with a +1 offset it is the 7th.
        $this->assertSame(6, CarbonMacros::nthWeekdayDay($july2026, 1, Carbon::MONDAY));
        $this->assertSame(7, CarbonMacros::nthWeekdayDay($july2026, 1, Carbon::MONDAY, 1));

        // A non-existent occurrence (no fifth Monday in February 2026) returns null.
        $this->assertNull(CarbonMacros::nthWeekdayDay(Carbon::create(2026, 2, 1), 5, Carbon::MONDAY));
    }
}
