<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
            'reverseString', 'countWords', 'removeAccents',
        ],
        'Stringable' => [
            'kebabToTitle', 'snakeToTitle', 'camelToTitle', 'truncateMiddle', 'isEmail',
            'stripWhitespace', 'normalizeWhitespace', 'toBool', 'wrapWith', 'reverseString',
            'countWords', 'removeAccents',
        ],
        'Collection' => [
            'transpose', 'recursive', 'mapToKey', 'filterRecursive', 'firstOrFail',
            'sumRecursive', 'averageBy', 'toCsv', 'prioritize', 'rotateLeft', 'rotateRight',
            'toTree', 'insertAfter', 'insertBefore',
        ],
        'Arr' => [
            'filterNulls', 'filterEmpty', 'mapKeys', 'insertAfter', 'insertBefore', 'removeValue',
            'removeValues', 'renameKey', 'average', 'median', 'groupByKey', 'uniqueBy', 'sortByKeys',
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
    ];

    /**
     * Documented removal record: every legacy macro/class dropped during the
     * consolidation, each with a one-line reason.
     *
     * @var array<string, string>
     */
    private const DROPPED = [
        // Carbon provider + all national holiday/date classes.
        'CarbonMacroProvider (all Carbon macros)' => 'Locale-specific holiday wiring; remaining date macros are niche/duplicative.',
        'BrazilianHolidays' => 'Locale-specific national holidays; low value.',
        'CanadianDates' => 'Locale-specific national dates; low value.',
        'DutchHolidays' => 'Locale-specific national holidays; low value.',
        'FrenchHolidays' => 'Locale-specific national holidays; low value.',
        'GermanHolidays' => 'Locale-specific national holidays; low value.',
        'IndianHolidays' => 'Locale-specific national holidays; low value.',
        'IndonesianHolidays' => 'Locale-specific national holidays; low value.',
        'ItalianHolidays' => 'Locale-specific national holidays; low value.',
        'KenyanHolidays' => 'Locale-specific national holidays; low value.',
        'SwedishHolidays' => 'Locale-specific national holidays; low value.',
        'UkrainianHolidays' => 'Locale-specific national holidays; low value.',
        'ZambianHolidays' => 'Locale-specific national holidays; low value.',
        'UsDates' => 'Locale-specific national dates; low value.',
        'MultiNationalDates' => 'Locale-specific national dates aggregator; low value.',

        // Response HTML/JSON macros — superseded by ApiResponseTrait.
        'ResponseMacroProvider' => 'Superseded by ApiResponseTrait; legacy HTML helpers emit unescaped markup (XSS risk).',
        'ResponseMacros' => 'HTML response helper; XSS risk; ApiResponseTrait is the canonical API.',
        'Error' => 'HTML response helper; XSS risk; dropped with ResponseMacros.',
        'Success' => 'HTML response helper; XSS risk; dropped with ResponseMacros.',
        'Pdf' => 'HTML/PDF response helper; out of scope and unsafe; dropped with ResponseMacros.',
        'Message' => 'Response message helper; dropped with ResponseMacros.',

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

        // Orphaned invokable micro-classes (unreferenced by any grouped provider).
        'Orphaned Macros/* micro-classes (After, Before, At, Bind, ChunkBy, Decrement, EachCons, Nth-ordinals, FilterMap, FirstOrPush, FromBase64/Json/Pairs, Glob, Head, Tail, Human, IfMacro, Interpolate, Ksort/Krsort/Rsort, Paginate*, ParallelMap, PluckMany, Recursive, RenameKeys, ReplaceInKeys, Rotate, Round5, SectionBy, SliceBefore, StripTags, ToBase64, ToPairs, Transpose, TryCatch, Validate, WhenEquals, Where*, WithSize, WordsCount, etc.)' => 'Unreferenced by any provider; dead code, or redundant with kept inline macros.',
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

    public function test_blueprint_macros_are_registered(): void
    {
        foreach (self::KEPT['Blueprint'] as $macro) {
            $this->assertTrue(Blueprint::hasMacro($macro), "Blueprint::{$macro} should be registered.");
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
}
