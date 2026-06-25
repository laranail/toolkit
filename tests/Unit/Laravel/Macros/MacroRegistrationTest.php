<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Documents the kept-vs-dropped macro inventory and asserts every kept macro is
 * registered after the toolkit boots.
 */
class MacroRegistrationTest extends TestCase
{
    /**
     * Kept macros, keyed by the class they are registered on.
     *
     * @var array<string, list<string>>
     */
    private const KEPT = [
        'Str' => [
            'kebabToTitle', 'snakeToTitle', 'camelToTitle', 'truncateMiddle', 'isEmail',
            'stripWhitespace', 'normalizeWhitespace', 'toBool', 'wrapWith', 'replaceMany',
            'matches', 'reverseString', 'countWords', 'removeAccents', 'readingMinutes', 'highlightWords',
            // Restored legacy string utilities (no native Str equivalent).
            'stripTags', 'linesCount', 'interpolate',
            // Native string-similarity (restores laravel-string-similarities, no pheg).
            'levenshtein', 'similarText', 'jaroWinkler', 'closest',
        ],
        'Stringable' => [
            'kebabToTitle', 'snakeToTitle', 'camelToTitle', 'truncateMiddle', 'isEmail',
            'stripWhitespace', 'normalizeWhitespace', 'toBool', 'wrapWith', 'matches', 'reverseString',
            'countWords', 'removeAccents', 'readingMinutes', 'highlightWords',
            // stripTags/fromBase64 are native Stringable methods — no macro.
            'linesCount', 'interpolate',
            'levenshtein', 'similarText', 'jaroWinkler', 'closest',
        ],
        'Collection' => [
            'transpose', 'recursive', 'mapToKey', 'filterRecursive', 'firstOrFail',
            'sumRecursive', 'averageBy', 'toCsv', 'prioritize', 'rotateLeft', 'rotateRight',
            'toTree', 'insertAfter', 'insertBefore',
            // G6a: navigation / positional.
            'before', 'insertAt', 'rotate', 'firstOrPush',
            // G6a: consecutive-window / predicate chunking.
            'eachCons', 'sliceBefore', 'chunkBy', 'groupByModel',
            // G6a: reshape / conditional.
            'forSelectBox', 'extract', 'tail', 'toPairs', 'fromPairs', 'ifEmpty',
            // G8a: key/value reshape + relevance sort (fold the broken legacy
            // Collection->select + CollectionHelperService::sortSearchResults).
            'mapKeyValuePairs', 'sortSearchResults',
            // Restored borderline macros (legacy PluckMany / ReplaceInKeys).
            'pluckMany', 'replaceInKeys',
            // Restored legacy Collection micro-classes (filter / lookup / insert).
            // (after is native in this Laravel version — not re-registered.)
            'collectBy', 'filterMap', 'ifAny', 'none', 'pluckToArray',
            'withSize', 'insertAfterKey', 'insertBeforeKey', 'sectionBy',
            // Restored legacy deep-path string filters (no native equivalent;
            // strict non-string guard fixes the loose legacy versions).
            'whereContains', 'whereStartsWith', 'whereEndsWith',
        ],
        'Arr' => [
            'filterNulls', 'filterEmpty', 'mapKeys', 'insertAfter', 'insertBefore', 'removeValue',
            'removeValues', 'renameKey', 'average', 'median', 'groupByKey', 'uniqueBy', 'sortByKeys',
            // G6a: multi-rename map.
            'renameKeys',
        ],
        'QueryBuilder' => [
            'whenFilled', 'whereBetweenDates', 'orderByNullsLast', 'orderByNullsFirst', 'log',
        ],
        'EloquentBuilder' => [
            'whenFilled', 'whereBetweenDates', 'existsOr', 'doesntExistOr',
        ],
        'Request' => [
            'expectsJsonOrAjax', 'isBot', 'isFromMobile', 'hasFiles', 'hasValidFile', 'getReferer',
            'isFromDomain', 'isJsonRequest', 'onlyFilled', 'hasAny', 'mergeIfMissing',
        ],
        // Response macros delegate to the canonical ApiResponseTrait envelope.
        'Response' => [
            'success', 'error', 'message', 'pdf',
        ],
        'Carbon' => [
            // General-purpose date / business-day helpers that are NOT native in
            // Carbon 3 (startOfQuarter/endOfQuarter/isSameQuarter/isWeekday/
            // nextWeekday/previousWeekday/toDateTimeLocalString are native — dropped).
            'fromDateTimeLocalString', 'addBusinessDays', 'subBusinessDays',
            'isLastDayOfMonth', 'isFirstDayOfMonth', 'toHumanReadableString', 'getQuarter',
            // Multi-national (Christian feasts + new year).
            'isNewYearsDay', 'isEasterSunday', 'isGoodFriday', 'isAllSaintsDay', 'isChristmasDay', 'isNewYearsEve',
            // Brazilian.
            'isTiradentesDay', 'isBrazilianLaborDay', 'isBrazilianIndependenceDay', 'isTheDayOfOurLadyAparecida',
            'isBrazilianDayOfTheDead', 'isBrazilianRepublicProclamationDay',
            // Canadian.
            'isVictoriaDay', 'isCanadaDay', 'isLabourDay', 'isCanadianThanksgiving', 'isRemembranceDay',
            'isBoxingDay', 'isCivicHoliday', 'isFamilyDay',
            // Dutch.
            'isDutchLiberationDay', 'isSaintNicholasEve', 'isDutchRemembranceDay', 'isDutchNationalDay',
            // French.
            'isAscensionDay', 'isAssumptionDay', 'isEasterMonday', 'isFirstWarArmisticeDay', 'isFrenchNationalDay',
            'isPentecostDay', 'isSecondWarArmisticeDay',
            // German.
            'isGermanLabourDay', 'isAscensionOfJesus', 'isWhitSunday', 'isWhitsun', 'isPentecost',
            'isPentecostSunday', 'isWhitMonday', 'isPentecostMonday', 'isCorpusChristi', 'isGermanUnityDay',
            'isHeiligerAbend', 'isHeiligAbend',
            // Indian.
            'isIndianRepublicDay', 'isIndianIndependenceDay', 'isGandhiJayanti',
            // Indonesian.
            'isIndonesianIndependenceDay', 'isPancasilaDay', 'isIndonesianLaborDay', 'isKartiniDay',
            'isIndonesianEducationDay', 'isIndonesiaCustomerDay', 'isIndonesianHeroesDay', 'isIndonesianMothersDay',
            // Italian.
            'isLiberationDay', 'isRepublicDay', 'isImmaculateConceptionFeast', 'isAssumptionOfMaryFeast',
            'isEpiphany', 'isSaintStephenDay', 'isSaintSylvesterDay', 'isWorkersDay',
            // Kenyan.
            'isKenyanIndependenceDay', 'isKenyanJamhuriDay', 'isKenyanLabourDay', 'isKenyanMadarakaDay',
            'isKenyanHudumaDay', 'isKenyanMashujaaDay', 'isKenyanUtamaduniDay',
            // Swedish.
            'isSwedishMidsummerDay', 'isChristmasEve', 'isSwedishNationalDay',
            // Ukrainian.
            'isUkrainianIndependenceDay', 'isUkraineDefenderDay', 'isUkrainianConstitutionDay',
            'isUkrainianLabourDay', 'isKupalaNight', 'isVictoryDayOverNazism',
            // US.
            'isMlkJrDay', 'isIndependenceDay', 'isMemorialDay', 'isLaborDay', 'isVeteransDay',
            'isAmericanThanksgiving', 'isPresidentsDay', 'isColumbusDay',
            // Zambian.
            'isZambianIndependenceDay', 'isZambianLabourDay', 'isZambianYouthDay', 'isZambianWomensDay',
            'isZambianAfricanUnityDay', 'isZambianAfricaDay', 'isZambianHeroesDay', 'isZambianUnityDay',
            'isZambianFarmersDay', 'isZambianNationalPrayerDay',
        ],
    ];

    /**
     * Documented removal record: every legacy macro/class dropped during the
     * consolidation, each with a one-line reason.
     *
     * @var array<string, string>
     */
    private const DROPPED = [
        // Carbon date/quarter/business-day macros + every national holiday/date
        // calendar are now PORTED into Macros\CarbonMacros (G3) — see the
        // 'Carbon' group in self::KEPT and the per-calendar behaviour tests.
        // The only Carbon-area class still dropped is DistanceBetween (below).

        // Geo macro: orphaned invokable, never wired to any Macroable target,
        // and pheg-dependent. No clean target class to extend, so kept dropped.
        'DistanceBetween' => 'Orphaned pheg-dependent geo invokable; never registered on any target class.',

        // Concurrency macro: needs amphp/parallel-functions; native Laravel
        // concurrency exists, so dropped rather than pulling the dep.
        'ParallelMap' => 'Requires amphp/parallel-functions; native Laravel concurrency supersedes it.',

        // Carbon date macros that are native in Carbon 3 — the legacy macros only
        // shadowed them, so they are not re-registered. (The remaining Carbon
        // date helpers + every holiday calendar ARE ported — see KEPT['Carbon'].)
        'Carbon::startOfQuarter / endOfQuarter / isSameQuarter' => 'Native Carbon 3 methods; legacy macros shadowed them.',
        'Carbon::isWeekday' => 'Native Carbon method; the legacy macro shadowed it.',
        'Carbon::nextWeekday / previousWeekday' => 'Native Carbon mutating modifiers; legacy macros shadowed them.',
        'Carbon::toDateTimeLocalString' => 'Native Carbon method (with a $precision arg); the legacy macro shadowed it.',

        // Response macros — RESTORED into Macros\ResponseMacros, delegating to
        // the canonical ApiResponseTrait envelope (see KEPT['Response']). The
        // legacy ResponseMacroProvider/ResponseMacros wiring classes stay dropped
        // (replaced by the grouped provider); their behaviour lives on as the
        // success/error/message/pdf macros.
        'ResponseMacroProvider' => 'Replaced by Macros\\ResponseMacros (grouped provider); legacy wiring class no longer needed.',
        'ResponseMacros (legacy wiring class)' => 'Replaced by Macros\\ResponseMacros; the success/error/message/pdf macros are restored there.',

        // Dropped individual macros from kept providers.
        'Str::wrap / Stringable::wrap' => 'Native in Laravel; kept as wrapWith to avoid overriding core.',
        'Str::reverse / Stringable::reverse' => 'Native in Laravel; kept as reverseString to avoid overriding core.',
        'Str::unwrap / Stringable::unwrap' => 'Native in Laravel; the macro was shadowed by the core method.',
        'Str::isUrl / Stringable::isUrl' => 'Native in Laravel; the macro was shadowed by the core method.',
        'Str::initials / Stringable::initials' => 'Native in Laravel and behaves identically; the macro was shadowed.',
        'Str::replaceFirst / Str::replaceLast' => 'Native Laravel methods; macro was a redundant fallback.',
        'Collection::chunkWhileNull' => 'Broken: called chunk() with the wrong signature.',
        'Collection::countBy' => 'Native Collection method.',
        'Arr::keyExists' => 'Trivial alias of array_key_exists()/Arr::exists().',
        'Arr::isAssoc' => 'Trivial self-call of native Arr::isAssoc().',
        'Arr::flattenWithDepth' => 'Trivial pass-through to native Arr::flatten().',
        'Builder::whereLike / whereNotLike / orWhereLike' => 'Native in Laravel 12 query builder; the macros were shadowed.',
        'QueryBuilder::toBase' => 'Shadowed native toBase() and just returned $this.',
        'Builder::dumpSql / ddSql' => 'Debug-only dump()/dd() helpers; low value and dd() halts execution.',
        'Blueprint::dropIndexIfExists' => 'Relied on the removed Doctrine schema manager API; dead/broken.',
        'Request::isAjax / getUserAgent / isHttps / getClientIps / getBearerToken / getFullUrlWithQuery / getQueryString' => 'Trivial one-to-one aliases of native Request methods.',
        'Request::getPreferredLanguage' => 'Macro recursed into the native method of the same name (infinite loop).',

        // G6a: useful Collection/Str micro-classes folded into the grouped macro
        // providers. The short name changes (each is now a registered macro, not
        // a class) — see KEPT['Collection']/['Str']/['Arr'] and the ledger.
        'Macros\\Before / InsertAt / Rotate / FirstOrPush / EachCons / SliceBefore / ChunkBy / GroupByModel / ForSelectBox / Extract / Tail / ToPairs / FromPairs / IfEmpty' => 'Folded into Macros\\CollectionMacros as registered Collection macros (G6a).',
        'Macros\\RenameKeys' => 'Folded into Macros\\ArrMacros as the Arr::renameKeys multi-rename macro (G6a).',
        'Macros\\ReadingMinutes / HighlightWords' => 'Folded into Macros\\StringMacros as Str/Stringable macros (G6a); HighlightWords emits e()-escaped HtmlString.',

        // Native-duplicative / broken legacy macros, kept dropped.
        'Str::initials / Stringable::initials (legacy Macros\\Initials)' => 'Native Str::initials exists; the legacy macro called a nonexistent Str::interpolate.',

        // Restored borderline macros: folded into the grouped providers.
        'Macros\\Matches' => 'Folded into Macros\\StringMacros as the Str/Stringable matches() macro (native preg_match wrapper, returns bool).',
        'Macros\\PluckMany / ReplaceInKeys' => 'Folded into Macros\\CollectionMacros as registered Collection macros (pluckMany / replaceInKeys).',

        // RESTORED legacy Collection micro-classes → Macros\CollectionMacros.
        'Macros\\CollectBy / FilterMap / IfAny / None / PluckToArray / WithSize / InsertAfterKey / InsertBeforeKey / SectionBy' => 'Restored as registered Collection macros in Macros\\CollectionMacros (insert-by-key now key-preserving and non-mutating).',
        'Macros\\After' => 'Collection::after() is native in this Laravel version; the legacy After macro would be shadowed, so kept dropped.',

        // RESTORED legacy Str micro-classes → Macros\StringMacros.
        'Macros\\LinesCount / Interpolate' => 'Restored as Str + Stringable macros in Macros\\StringMacros (Interpolate re-implemented as :placeholder interpolation, longest-key-first).',
        'Macros\\StripTags' => 'Restored as the Str::stripTags macro only; Stringable::stripTags is native in Laravel (a macro there would be shadowed).',
        'Macros\\FromBase64' => 'Str::fromBase64 / Stringable::fromBase64 are native in Laravel (strictly-better for the bare-payload case); the data-URI variant cannot override the native method, so kept dropped.',

        // RESTORED legacy file/util micro-classes → Services\FileService (file domain).
        'Macros\\GenerateName / ToBase64 / FromJson' => 'Restored as FileService::generateName / FileService::toDataUri / FileService::fromJson (path-guarded; decode errors handled).',

        // Native-strictly-better legacy micro-classes, kept dropped.
        'Macros\\At / Second..Tenth (ordinals) / GetNth' => 'Positional access is the native $c->slice($n, 1)->first() / values()->get($n) / nth(); no macro adds value.',
        'Macros\\Head' => 'Trivial alias of the native Collection::first().',
        'Macros\\Ksort / Krsort / Rsort' => 'Native Collection::sortKeys() / sortKeysDesc() / sortDesc() are strictly better (immutable, keyed).',
        'Macros\\Path' => 'Native data_get()/Arr::get() dot-notation is strictly better.',
        'Macros\\Recursive / Transpose' => 'Already registered as inline Collection macros (recursive / transpose); legacy classes are redundant.',
        'Macros\\Increment / Decrement' => 'Mutated $this->items in place (anti-pattern); no clean immutable intent worth a macro.',
        'Macros\\CapitalizeWords' => 'Native Str::headline() does proper title-case and is strictly better; legacy called a nonexistent Str::capitalizeWords.',
        'Macros\\Human / Bind' => 'Broken: called nonexistent Str::human()/Str::bind(); no salvageable intent.',
        'Macros\\Round5' => 'Native round($n / 5) * 5 is a strictly simpler, correct one-liner (the legacy version mishandled negatives).',
        'Macros\\GetFile / Glob' => 'Trivial new SplFileInfo() / glob() wrappers; call PHP/File directly (per the FileService I/O policy).',
        'Macros\\FirstOrFail (legacy)' => 'Already registered as the inline Collection::firstOrFail() macro; legacy class is redundant.',
        'Macros\\Prioritize (legacy)' => 'Already registered as the inline Collection::prioritize() macro; legacy class is redundant.',
        'Macros\\InsertAfter / InsertBefore (legacy)' => 'Already registered as inline Collection::insertAfter()/insertBefore() macros.',
        'Macros\\WordsCount' => 'Already registered as the Str::countWords() macro (returns int, not a string).',
        'Macros\\WhereContains / WhereStartsWith / WhereEndsWith' => 'RESTORED as Collection::whereContains/whereStartsWith/whereEndsWith macros in Macros\\CollectionMacros — ergonomic deep-path string filters with no native equal; the strict non-string guard fixes the legacy versions that tripped on non-string column values.',

        // Anti-patterns / dependency-locked, kept dropped.
        'Macros\\CatchableProxy / TryCatch' => 'try/catch-over-collections anti-pattern; TryCatch also depends on spatie/laravel-collection-macros.',
        'Macros\\MacroSupport' => 'JSON round-trip "variable" coercion helper; lossy and not a Macroable target; dropped.',
        'Macros\\IfMacro' => 'value()-based conditional with no Macroable target; native when()/unless() cover it.',
        'Macros\\IsEquals / WhenEquals' => 'Request equality anti-pattern (loose == in WhenEquals); native $request->input() comparison is clearer.',
        'Macros\\Validate' => 'Resolves the validator per item inside a Collection predicate; heavyweight anti-pattern, dropped.',
        'Macros\\Paginate / Paginator / SimplePaginate / PaginateFirstDifferent / PaginateWithPrevious / FirstDifferentLengthAwarePaginator' => 'Bespoke paginators; native Collection paginate helpers / LengthAwarePaginator supersede them.',
    ];

    public function test_dropped_inventory_is_documented(): void
    {
        $this->assertNotEmpty(self::DROPPED);

        foreach (self::DROPPED as $name => $reason) {
            $this->assertIsString($name);
            $this->assertNotSame('', trim($reason), "Dropped entry [{$name}] needs a reason.");
        }
    }

    public function test_str_macros_are_registered(): void
    {
        foreach (self::KEPT['Str'] as $macro) {
            $this->assertTrue(Str::hasMacro($macro), "Str::{$macro} should be registered.");
        }
    }

    public function test_stringable_macros_are_registered(): void
    {
        foreach (self::KEPT['Stringable'] as $macro) {
            $this->assertTrue(Stringable::hasMacro($macro), "Stringable::{$macro} should be registered.");
        }
    }

    public function test_collection_macros_are_registered(): void
    {
        foreach (self::KEPT['Collection'] as $macro) {
            $this->assertTrue(Collection::hasMacro($macro), "Collection::{$macro} should be registered.");
        }
    }

    public function test_arr_macros_are_registered(): void
    {
        foreach (self::KEPT['Arr'] as $macro) {
            $this->assertTrue(Arr::hasMacro($macro), "Arr::{$macro} should be registered.");
        }
    }

    public function test_query_builder_macros_are_registered(): void
    {
        foreach (self::KEPT['QueryBuilder'] as $macro) {
            $this->assertTrue(QueryBuilder::hasMacro($macro), "QueryBuilder::{$macro} should be registered.");
        }
    }

    public function test_eloquent_builder_macros_are_registered(): void
    {
        foreach (self::KEPT['EloquentBuilder'] as $macro) {
            $this->assertTrue(EloquentBuilder::hasGlobalMacro($macro), "EloquentBuilder::{$macro} should be registered.");
        }
    }

    public function test_request_macros_are_registered(): void
    {
        foreach (self::KEPT['Request'] as $macro) {
            $this->assertTrue(Request::hasMacro($macro), "Request::{$macro} should be registered.");
        }

        // Also assert via the facade-backed request instance path.
        $this->assertTrue(new Request()->hasMacro('isBot'));
    }

    public function test_carbon_macros_are_registered(): void
    {
        foreach (self::KEPT['Carbon'] as $macro) {
            $this->assertTrue(Carbon::hasMacro($macro), "Carbon::{$macro} should be registered.");
        }
    }

    public function test_response_macros_are_registered(): void
    {
        foreach (self::KEPT['Response'] as $macro) {
            $this->assertTrue(Response::hasMacro($macro), "Response::{$macro} should be registered.");
        }
    }
}
