<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Guards the committed IDE-helper stub (ide-helper/_ide_helper_macros.php)
 * against drift: the `@method` tags it declares per macroable target must match
 * exactly the macros the toolkit registers at boot. If a macro is added/renamed
 * in a *Macros.php provider without updating the stub (or vice versa), this test
 * fails so the autocomplete aid can never silently go stale.
 */
class IdeHelperStubTest extends TestCase
{
    private const STUB_PATH = __DIR__ . '/../../../../ide-helper/_ide_helper_macros.php';

    /**
     * The fully-qualified stub class each macroable target's macros are declared
     * on, plus a runtime predicate used to assert the macro is really registered.
     *
     * @var array<string, array{class: class-string, has: callable(string): bool}>
     */
    private function targets(): array
    {
        return [
            'Str' => [
                'class' => Str::class,
                'has' => Str::hasMacro(...),
            ],
            'Stringable' => [
                'class' => Stringable::class,
                'has' => Stringable::hasMacro(...),
            ],
            'Collection' => [
                'class' => Collection::class,
                'has' => Collection::hasMacro(...),
            ],
            'Arr' => [
                'class' => Arr::class,
                'has' => Arr::hasMacro(...),
            ],
            'Carbon' => [
                'class' => Carbon::class,
                'has' => Carbon::hasMacro(...),
            ],
            'QueryBuilder' => [
                'class' => QueryBuilder::class,
                'has' => QueryBuilder::hasMacro(...),
            ],
            'EloquentBuilder' => [
                'class' => EloquentBuilder::class,
                'has' => EloquentBuilder::hasGlobalMacro(...),
            ],
            'Blueprint' => [
                'class' => Blueprint::class,
                'has' => Blueprint::hasMacro(...),
            ],
            'Request' => [
                'class' => Request::class,
                'has' => Request::hasMacro(...),
            ],
            'ResponseFactory' => [
                'class' => ResponseFactory::class,
                'has' => ResponseFactory::hasMacro(...),
            ],
        ];
    }

    /**
     * The toolkit's registered-macro inventory, keyed by target label. Mirrors
     * MacroRegistrationTest::KEPT — both this and the stub must list every macro,
     * so adding a macro means updating the provider, that test, this inventory,
     * and the stub together (the three tests fail until they agree).
     *
     * @return array<string, list<string>>
     */
    private function registeredInventory(): array
    {
        return [
            'Str' => [
                'kebabToTitle', 'snakeToTitle', 'camelToTitle', 'truncateMiddle', 'isEmail',
                'stripWhitespace', 'normalizeWhitespace', 'toBool', 'wrapWith', 'replaceMany',
                'matches', 'reverseString', 'countWords', 'removeAccents', 'readingMinutes', 'highlightWords',
                'stripTags', 'linesCount', 'interpolate',
            ],
            'Stringable' => [
                'kebabToTitle', 'snakeToTitle', 'camelToTitle', 'truncateMiddle', 'isEmail',
                'stripWhitespace', 'normalizeWhitespace', 'toBool', 'wrapWith', 'matches', 'reverseString',
                'countWords', 'removeAccents', 'readingMinutes', 'highlightWords',
                'linesCount', 'interpolate',
            ],
            'Collection' => [
                'transpose', 'recursive', 'mapToKey', 'filterRecursive', 'firstOrFail',
                'sumRecursive', 'averageBy', 'toCsv', 'prioritize', 'rotateLeft', 'rotateRight',
                'toTree', 'insertAfter', 'insertBefore',
                'before', 'insertAt', 'rotate', 'firstOrPush',
                'eachCons', 'sliceBefore', 'chunkBy', 'groupByModel',
                'forSelectBox', 'extract', 'tail', 'toPairs', 'fromPairs', 'ifEmpty',
                'mapKeyValuePairs', 'sortSearchResults',
                'pluckMany', 'replaceInKeys',
                'collectBy', 'filterMap', 'ifAny', 'none', 'pluckToArray',
                'withSize', 'insertAfterKey', 'insertBeforeKey', 'sectionBy',
            ],
            'Arr' => [
                'filterNulls', 'filterEmpty', 'mapKeys', 'insertAfter', 'insertBefore', 'removeValue',
                'removeValues', 'renameKey', 'average', 'median', 'groupByKey', 'uniqueBy', 'sortByKeys',
                'renameKeys',
            ],
            'QueryBuilder' => [
                'whenFilled', 'whereBetweenDates', 'orderByNullsLast', 'orderByNullsFirst', 'log',
            ],
            'EloquentBuilder' => [
                'whenFilled', 'whereBetweenDates', 'existsOr', 'doesntExistOr',
            ],
            'Blueprint' => [
                'addCommonFields', 'addUserFields', 'addPublishingFields', 'addStatusField',
                'addSortingField', 'addSlugField', 'dropForeignIfExists', 'dropColumnIfExists',
                'addMetaFields', 'addSeoFields', 'addLocationFields', 'addImageFields', 'addPriceFields',
                'addActivationFields', 'addExpiryFields', 'addUuidPrimaryKey', 'addNullableMorphs',
            ],
            'Request' => [
                'expectsJsonOrAjax', 'isBot', 'isFromMobile', 'hasFiles', 'hasValidFile', 'getReferer',
                'isFromDomain', 'isJsonRequest', 'onlyFilled', 'hasAny', 'mergeIfMissing',
            ],
            'ResponseFactory' => [
                'success', 'error', 'message', 'pdf',
            ],
            'Carbon' => [
                'fromDateTimeLocalString', 'addBusinessDays', 'subBusinessDays',
                'isLastDayOfMonth', 'isFirstDayOfMonth', 'toHumanReadableString', 'getQuarter',
                'isNewYearsDay', 'isEasterSunday', 'isGoodFriday', 'isAllSaintsDay', 'isChristmasDay', 'isNewYearsEve',
                'isTiradentesDay', 'isBrazilianLaborDay', 'isBrazilianIndependenceDay', 'isTheDayOfOurLadyAparecida',
                'isBrazilianDayOfTheDead', 'isBrazilianRepublicProclamationDay',
                'isVictoriaDay', 'isCanadaDay', 'isLabourDay', 'isCanadianThanksgiving', 'isRemembranceDay',
                'isBoxingDay', 'isCivicHoliday', 'isFamilyDay',
                'isDutchLiberationDay', 'isSaintNicholasEve', 'isDutchRemembranceDay', 'isDutchNationalDay',
                'isAscensionDay', 'isAssumptionDay', 'isEasterMonday', 'isFirstWarArmisticeDay', 'isFrenchNationalDay',
                'isPentecostDay', 'isSecondWarArmisticeDay',
                'isGermanLabourDay', 'isAscensionOfJesus', 'isWhitSunday', 'isWhitsun', 'isPentecost',
                'isPentecostSunday', 'isWhitMonday', 'isPentecostMonday', 'isCorpusChristi', 'isGermanUnityDay',
                'isHeiligerAbend', 'isHeiligAbend',
                'isIndianRepublicDay', 'isIndianIndependenceDay', 'isGandhiJayanti',
                'isIndonesianIndependenceDay', 'isPancasilaDay', 'isIndonesianLaborDay', 'isKartiniDay',
                'isIndonesianEducationDay', 'isIndonesiaCustomerDay', 'isIndonesianHeroesDay', 'isIndonesianMothersDay',
                'isLiberationDay', 'isRepublicDay', 'isImmaculateConceptionFeast', 'isAssumptionOfMaryFeast',
                'isEpiphany', 'isSaintStephenDay', 'isSaintSylvesterDay', 'isWorkersDay',
                'isKenyanIndependenceDay', 'isKenyanJamhuriDay', 'isKenyanLabourDay', 'isKenyanMadarakaDay',
                'isKenyanHudumaDay', 'isKenyanMashujaaDay', 'isKenyanUtamaduniDay',
                'isSwedishMidsummerDay', 'isChristmasEve', 'isSwedishNationalDay',
                'isUkrainianIndependenceDay', 'isUkraineDefenderDay', 'isUkrainianConstitutionDay',
                'isUkrainianLabourDay', 'isKupalaNight', 'isVictoryDayOverNazism',
                'isMlkJrDay', 'isIndependenceDay', 'isMemorialDay', 'isLaborDay', 'isVeteransDay',
                'isAmericanThanksgiving', 'isPresidentsDay', 'isColumbusDay',
                'isZambianIndependenceDay', 'isZambianLabourDay', 'isZambianYouthDay', 'isZambianWomensDay',
                'isZambianAfricanUnityDay', 'isZambianAfricaDay', 'isZambianHeroesDay', 'isZambianUnityDay',
                'isZambianFarmersDay', 'isZambianNationalPrayerDay',
            ],
        ];
    }

    public function test_stub_file_exists(): void
    {
        $this->assertFileExists(self::STUB_PATH, 'The IDE-helper macro stub is missing.');
    }

    public function test_stub_methods_match_registered_macros(): void
    {
        $contents = file_get_contents(self::STUB_PATH);
        $this->assertIsString($contents);

        foreach ($this->targets() as $label => $target) {
            $stubbed = $this->stubMethodsFor($contents, $target['class']);

            $this->assertNotEmpty($stubbed, "No @method tags found in the stub for {$label}.");

            foreach ($stubbed as $macro) {
                $this->assertTrue(
                    ($target['has'])($macro),
                    "Stub lists {$label}::{$macro}() but no such macro is registered.",
                );
            }
        }
    }

    public function test_stub_covers_every_registered_macro(): void
    {
        $contents = file_get_contents(self::STUB_PATH);
        $this->assertIsString($contents);

        foreach ($this->registeredInventory() as $label => $macros) {
            $stubbed = $this->stubMethodsFor($contents, $this->targets()[$label]['class']);

            foreach ($macros as $macro) {
                $this->assertContains(
                    $macro,
                    $stubbed,
                    "{$label}::{$macro}() is registered but missing from the IDE-helper stub.",
                );
            }
        }
    }

    public function test_stub_documents_the_factory_mixin(): void
    {
        $contents = file_get_contents(self::STUB_PATH);
        $this->assertIsString($contents);

        $stubbed = $this->stubMethodsFor(
            $contents,
            Factory::class,
        );

        $this->assertSame(['withoutEvents'], $stubbed, 'The Factory mixin stub must list withoutEvents().');
    }

    /**
     * Extract the `@method` names declared on a given stub class. The stub is a
     * static aid that is never autoloaded, so it is parsed textually here rather
     * than reflected (reflecting it would re-declare the framework classes).
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    private function stubMethodsFor(string $contents, string $class): array
    {
        $namespace = trim(substr($class, 0, (int) strrpos($class, '\\')), '\\');
        $shortName = substr($class, (int) strrpos($class, '\\') + 1);

        // Isolate the `namespace <ns> { … }` block, then the docblock immediately
        // before `class <ShortName> {}` inside it.
        $nsPattern = '/namespace\s+' . preg_quote($namespace, '/') . '\s*\{(.*?)\n\}/s';

        if (preg_match($nsPattern, $contents, $nsMatch) !== 1) {
            return [];
        }

        $block = $nsMatch[1];
        // Tempered match so the docblock cannot swallow an earlier class's `*/`:
        // capture only the comment immediately preceding `class <ShortName> {}`.
        $classPattern = '#/\*\*((?:(?!\*/).)*)\*/\s*class\s+' . preg_quote($shortName, '#') . '\s*\{\}#s';

        if (preg_match($classPattern, $block, $classMatch) !== 1) {
            return [];
        }

        preg_match_all(
            '/@method\s+(?:static\s+)?[^\s]+(?:\|[^\s]+)*\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/',
            $classMatch[1],
            $methodMatches,
        );

        return array_values(array_unique($methodMatches[1]));
    }
}
