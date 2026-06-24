<?php

/**
 * laranail/toolkit — IDE helper stub for runtime-registered macros.
 *
 * The toolkit registers many macros on Illuminate's macroable targets
 * (Str, Stringable, Collection, Arr, the query/Eloquent builders, Blueprint,
 * Request, Carbon) plus a Factory mixin. Those are added at boot via
 * `Macroable::macro()` / `Factory::mixin()`, so a static analyser / IDE cannot
 * see them and will not autocomplete or type them.
 *
 * This file re-opens each Illuminate namespace and re-declares the real class
 * with `@method` / `@method static` PHPDoc tags for every macro the toolkit
 * registers. PhpStorm and VS Code (Intelephense) MERGE these `@method` tags
 * onto the real class when indexing, giving full autocomplete + signatures.
 *
 * IMPORTANT — this file is NEVER loaded at runtime:
 *   - it is NOT listed in composer.json `autoload.files`;
 *   - it lives OUTSIDE the `src/` PSR-4 root, so it is never autoloaded;
 *   - IDEs index it statically (they parse, they do not execute it), so the
 *     re-declared classes never collide with the framework's real ones.
 *
 * Do NOT `require` this file. Do NOT add it to composer autoload. It is a
 * static aid only. If you change a `*Macros.php` provider, regenerate the
 * matching `@method` line here — the
 * `tests/Unit/Laravel/Macros/IdeHelperStubTest.php` drift test fails the build
 * if this stub and the registered macros diverge.
 *
 * @noinspection ALL
 *
 * phpcs:ignoreFile
 */

namespace Illuminate\Support {
    /**
     * @method static string kebabToTitle(string $string) Title-case a kebab-cased string ("a-b" → "A B").
     * @method static string snakeToTitle(string $string) Title-case a snake_cased string ("a_b" → "A B").
     * @method static string camelToTitle(string $string) Title-case a camelCased string ("aB" → "A B").
     * @method static string truncateMiddle(string $string, int $length = 50, string $middle = '...') Truncate the middle of a string, keeping both ends.
     * @method static bool isEmail(string $string) Whether the string is a valid email address.
     * @method static string stripWhitespace(string $string) Remove every whitespace character.
     * @method static string normalizeWhitespace(string $string) Collapse runs of whitespace to a single space and trim.
     * @method static bool toBool(string $string) Parse "1/true/yes/on" (case-insensitive) into a boolean.
     * @method static string wrapWith(string $string, string $wrapper = '"') Wrap the string in the given wrapper on both sides.
     * @method static string replaceMany(string $string, array $replacements) Apply an [search => replace] map in order.
     * @method static bool matches(string $string, string $pattern) Whether the string matches the full PCRE pattern.
     * @method static string reverseString(string $string) Reverse the string (non-shadowing alias of native reverse).
     * @method static int countWords(string $string) Count the words in the string.
     * @method static string removeAccents(string $string) Transliterate accented characters to ASCII.
     * @method static int readingMinutes(string $string, int $wordsPerMinute = 200) Estimated reading time, rounded up to whole minutes (min 1).
     * @method static \Illuminate\Support\HtmlString highlightWords(string $string, string|array $words) Wrap matched term(s) in <mark>…</mark>; XSS-safe HtmlString.
     */
    class Str {}

    /**
     * @method \Illuminate\Support\Stringable kebabToTitle() Title-case a kebab-cased string ("a-b" → "A B").
     * @method \Illuminate\Support\Stringable snakeToTitle() Title-case a snake_cased string ("a_b" → "A B").
     * @method \Illuminate\Support\Stringable camelToTitle() Title-case a camelCased string ("aB" → "A B").
     * @method \Illuminate\Support\Stringable truncateMiddle(int $length = 50, string $middle = '...') Truncate the middle of the string, keeping both ends.
     * @method bool isEmail() Whether the string is a valid email address.
     * @method \Illuminate\Support\Stringable stripWhitespace() Remove every whitespace character.
     * @method \Illuminate\Support\Stringable normalizeWhitespace() Collapse runs of whitespace to a single space and trim.
     * @method bool toBool() Parse "1/true/yes/on" (case-insensitive) into a boolean.
     * @method \Illuminate\Support\Stringable wrapWith(string $wrapper = '"') Wrap the string in the given wrapper on both sides.
     * @method bool matches(string $pattern) Whether the string matches the full PCRE pattern.
     * @method \Illuminate\Support\Stringable reverseString() Reverse the string (non-shadowing alias of native reverse).
     * @method int countWords() Count the words in the string.
     * @method \Illuminate\Support\Stringable removeAccents() Transliterate accented characters to ASCII.
     * @method int readingMinutes(int $wordsPerMinute = 200) Estimated reading time, rounded up to whole minutes (min 1).
     * @method \Illuminate\Support\HtmlString highlightWords(string|array $words) Wrap matched term(s) in <mark>…</mark>; XSS-safe HtmlString.
     */
    class Stringable {}

    /**
     * @method \Illuminate\Support\Collection transpose() Swap rows and columns of a collection of rows.
     * @method \Illuminate\Support\Collection recursive() Recursively wrap nested arrays/objects into collections.
     * @method \Illuminate\Support\Collection mapToKey(callable $callback) Map each item to a [key, value] pair, re-keying the collection.
     * @method \Illuminate\Support\Collection filterRecursive(?callable $callback = null) Filter the collection recursively through nested arrays/collections.
     * @method mixed firstOrFail(?callable $callback = null, mixed $default = null) First matching item, or throw RuntimeException when none.
     * @method mixed sumRecursive(mixed $key = null) Sum every (optionally keyed) value after flattening.
     * @method mixed averageBy(callable $callback) Average of the values produced by the callback.
     * @method array toCsv(string $delimiter = ',', string $enclosure = '"', string $escape = '\\') Each row rendered as a delimited, enclosed CSV line.
     * @method \Illuminate\Support\Collection prioritize(callable $callback) Move items matching the callback to the front.
     * @method \Illuminate\Support\Collection rotateLeft(int $count = 1) Rotate items left by the given count.
     * @method \Illuminate\Support\Collection rotateRight(int $count = 1) Rotate items right by the given count.
     * @method \Illuminate\Support\Collection toTree(string $parentKey = 'parent_id', string $childrenKey = 'children') Build a nested parent/children tree from flat rows.
     * @method \Illuminate\Support\Collection insertAfter(mixed $key, mixed $value) Insert a keyed value after the given key.
     * @method \Illuminate\Support\Collection insertBefore(mixed $key, mixed $value) Insert a keyed value before the given key.
     * @method mixed before(mixed $current, bool $strict = false) The item before $current (mirror of native after()); null if first/absent.
     * @method \Illuminate\Support\Collection insertAt(int $index, mixed $item, mixed $key = null) Insert an item at a positional index (non-mutating).
     * @method \Illuminate\Support\Collection rotate(int $offset) Rotate items by a (possibly negative) signed offset.
     * @method mixed firstOrPush(callable $callback, mixed $value, ?\Illuminate\Support\Collection $instance = null) First match, else push value($value) and return it.
     * @method \Illuminate\Support\Collection eachCons(int $chunkSize, bool $preserveKeys = false) Consecutive overlapping windows of $chunkSize items.
     * @method \Illuminate\Support\Collection sliceBefore(callable $callback, bool $preserveKeys = false) Split into chunks before each point the callback flags.
     * @method \Illuminate\Support\Collection chunkBy(callable $callback, bool $preserveKeys = false) Chunk while the callback's value over consecutive items is unchanged.
     * @method \Illuminate\Support\Collection groupByModel(callable|string $callback, mixed $modelKey = 0, mixed $itemsKey = 1) Group by a resolved Eloquent model into [model, items] rows.
     * @method array forSelectBox(string $key, string $value, bool $addEmpty = true) Sorted [key => value] options for a select box.
     * @method \Illuminate\Support\Collection extract(mixed $keys) Pull the given keys in order (null for missing), dropping keys for list().
     * @method \Illuminate\Support\Collection tail(bool $preserveKeys = false) Everything except the first item.
     * @method \Illuminate\Support\Collection toPairs() Convert to a collection of [key, value] pairs.
     * @method \Illuminate\Support\Collection fromPairs() Convert a collection of [key, value] pairs back to an associative collection.
     * @method \Illuminate\Support\Collection ifEmpty(callable $callback) Run the callback when empty, then return the collection.
     * @method \Illuminate\Support\Collection mapKeyValuePairs() Map {key, value} rows into an associative [key => value] collection.
     * @method \Illuminate\Support\Collection sortSearchResults(string $searchTerms, string $column) Sort by search relevance against the given column.
     * @method \Illuminate\Support\Collection pluckMany(array $keys) Reduce each item to only the given keys.
     * @method \Illuminate\Support\Collection replaceInKeys(string|array $search, string|array $replace) Run str_replace over every key, preserving values.
     */
    class Collection {}

    /**
     * @method static array filterNulls(array $array) Remove entries whose value is null.
     * @method static array filterEmpty(array $array) Remove entries whose value is empty().
     * @method static array mapKeys(array $array, callable $callback) Re-key the array using callback($key, $value).
     * @method static array insertAfter(array $array, mixed $key, array $insert) Insert entries after the given key.
     * @method static array insertBefore(array $array, mixed $key, array $insert) Insert entries before the given key.
     * @method static array removeValue(array $array, mixed $value) Remove every element strictly equal to $value.
     * @method static array removeValues(array $array, array $values) Remove every element present in $values.
     * @method static array renameKey(array $array, mixed $oldKey, mixed $newKey) Rename a single key, preserving order.
     * @method static array renameKeys(array $array, array $changes) Rename many keys from an [old => new] map; missing keys skipped.
     * @method static float|int average(array $array, ?string $key = null) Mean of the numeric (optionally plucked) values.
     * @method static float|int median(array $array, ?string $key = null) Median of the numeric (optionally plucked) values.
     * @method static array groupByKey(array $array, string $key) Group rows by the value at the given key.
     * @method static array uniqueBy(array $array, string $key) Drop later rows sharing a value at the given key.
     * @method static array sortByKeys(array $array, array $keys) Order the array's keys to match the given key order.
     */
    class Arr {}

    /**
     * @method static \Illuminate\Support\Carbon|null fromDateTimeLocalString(string $string) Parse a "Y-m-d\TH:i" datetime-local string, or null on failure.
     * @method \Illuminate\Support\Carbon addBusinessDays(int $days) Add the given number of weekdays.
     * @method \Illuminate\Support\Carbon subBusinessDays(int $days) Subtract the given number of weekdays.
     * @method bool isLastDayOfMonth() Whether the date is the last day of its month.
     * @method bool isFirstDayOfMonth() Whether the date is the first day of its month.
     * @method string toHumanReadableString() A friendly "Today/Yesterday/Tomorrow at … " or dated string.
     * @method int getQuarter() The quarter (1–4) of the date.
     * @method bool isNewYearsDay() Whether the date is January 1st.
     * @method bool isEasterSunday() Whether the date is Easter Sunday.
     * @method bool isGoodFriday() Whether the date is Good Friday.
     * @method bool isAllSaintsDay() Whether the date is All Saints' Day (Nov 1).
     * @method bool isChristmasDay() Whether the date is Christmas Day (Dec 25).
     * @method bool isNewYearsEve() Whether the date is New Year's Eve (Dec 31).
     * @method bool isTiradentesDay() Brazil: Tiradentes' Day (Apr 21).
     * @method bool isBrazilianLaborDay() Brazil: Labour Day (May 1).
     * @method bool isBrazilianIndependenceDay() Brazil: Independence Day (Sep 7).
     * @method bool isTheDayOfOurLadyAparecida() Brazil: Our Lady Aparecida (Oct 12).
     * @method bool isBrazilianDayOfTheDead() Brazil: Day of the Dead (Nov 2).
     * @method bool isBrazilianRepublicProclamationDay() Brazil: Republic Proclamation Day (Nov 15).
     * @method bool isVictoriaDay() Canada: Victoria Day (Monday before May 25).
     * @method bool isCanadaDay() Canada: Canada Day (Jul 1).
     * @method bool isLabourDay() Canada: Labour Day (first Monday of September).
     * @method bool isCanadianThanksgiving() Canada: Thanksgiving (second Monday of October).
     * @method bool isRemembranceDay() Canada: Remembrance Day (Nov 11).
     * @method bool isBoxingDay() Boxing Day (Dec 26).
     * @method bool isCivicHoliday() Canada: Civic Holiday (first Monday of August).
     * @method bool isFamilyDay() Canada: Family Day (third Monday of February).
     * @method bool isDutchLiberationDay() Netherlands: Liberation Day (May 5).
     * @method bool isSaintNicholasEve() Netherlands: Sinterklaas (Dec 5).
     * @method bool isDutchRemembranceDay() Netherlands: Remembrance Day (May 4).
     * @method bool isDutchNationalDay() Netherlands: King's/Queen's Day.
     * @method bool isAscensionDay() France: Ascension (39 days after Easter).
     * @method bool isAssumptionDay() France: Assumption (Aug 15).
     * @method bool isEasterMonday() Easter Monday (day after Easter Sunday).
     * @method bool isFirstWarArmisticeDay() France: WWI Armistice Day (Nov 11).
     * @method bool isFrenchNationalDay() France: Bastille Day (Jul 14).
     * @method bool isPentecostDay() France: Pentecost (49 days after Easter).
     * @method bool isSecondWarArmisticeDay() France: WWII Armistice Day (May 8).
     * @method bool isGermanLabourDay() Germany: Labour Day (May 1).
     * @method bool isAscensionOfJesus() Germany: Ascension (39 days after Easter).
     * @method bool isWhitSunday() Germany: Whit Sunday / Pentecost (49 days after Easter).
     * @method bool isWhitsun() Germany: alias of Whit Sunday.
     * @method bool isPentecost() Germany: alias of Whit Sunday.
     * @method bool isPentecostSunday() Germany: alias of Whit Sunday.
     * @method bool isWhitMonday() Germany: Whit Monday (day after Pentecost).
     * @method bool isPentecostMonday() Germany: alias of Whit Monday.
     * @method bool isCorpusChristi() Germany: Corpus Christi (60 days after Easter).
     * @method bool isGermanUnityDay() Germany: Unity Day (Oct 3).
     * @method bool isHeiligerAbend() Germany: Christmas Eve.
     * @method bool isHeiligAbend() Germany: alias of Christmas Eve.
     * @method bool isIndianRepublicDay() India: Republic Day (Jan 26).
     * @method bool isIndianIndependenceDay() India: Independence Day (Aug 15).
     * @method bool isGandhiJayanti() India: Gandhi Jayanti (Oct 2).
     * @method bool isIndonesianIndependenceDay() Indonesia: Independence Day (Aug 17).
     * @method bool isPancasilaDay() Indonesia: Pancasila Day (Jun 1).
     * @method bool isIndonesianLaborDay() Indonesia: Labour Day (May 1).
     * @method bool isKartiniDay() Indonesia: Kartini Day (Apr 21).
     * @method bool isIndonesianEducationDay() Indonesia: Education Day (May 2).
     * @method bool isIndonesiaCustomerDay() Indonesia: Customer Day (Sep 4).
     * @method bool isIndonesianHeroesDay() Indonesia: Heroes' Day (Nov 10).
     * @method bool isIndonesianMothersDay() Indonesia: Mothers' Day (Dec 22).
     * @method bool isLiberationDay() Italy: Liberation Day (Apr 25).
     * @method bool isRepublicDay() Italy: Republic Day (Jun 2).
     * @method bool isImmaculateConceptionFeast() Italy: Immaculate Conception (Dec 8).
     * @method bool isAssumptionOfMaryFeast() Italy: Assumption of Mary (Aug 15).
     * @method bool isEpiphany() Italy: Epiphany (Jan 6).
     * @method bool isSaintStephenDay() Italy: Saint Stephen's Day (Dec 26).
     * @method bool isSaintSylvesterDay() Italy: Saint Sylvester's Day (Dec 31).
     * @method bool isWorkersDay() Italy: Workers' Day (May 1, Apr 21 1924–1945).
     * @method bool isKenyanIndependenceDay() Kenya: Independence Day / Jamhuri (Dec 12).
     * @method bool isKenyanJamhuriDay() Kenya: alias of Jamhuri Day.
     * @method bool isKenyanLabourDay() Kenya: Labour Day (May 1).
     * @method bool isKenyanMadarakaDay() Kenya: Madaraka Day (Jun 1).
     * @method bool isKenyanHudumaDay() Kenya: Huduma Day (Oct 10).
     * @method bool isKenyanMashujaaDay() Kenya: Mashujaa Day (Oct 20).
     * @method bool isKenyanUtamaduniDay() Kenya: Utamaduni Day (Boxing Day, Dec 26).
     * @method bool isSwedishMidsummerDay() Sweden: Midsummer Day (Saturday, Jun 20–26).
     * @method bool isChristmasEve() Christmas Eve (Dec 24).
     * @method bool isSwedishNationalDay() Sweden: National Day (Jun 6).
     * @method bool isUkrainianIndependenceDay() Ukraine: Independence Day (Aug 24).
     * @method bool isUkraineDefenderDay() Ukraine: Defender Day (Oct 14).
     * @method bool isUkrainianConstitutionDay() Ukraine: Constitution Day (Jun 28).
     * @method bool isUkrainianLabourDay() Ukraine: Labour Day (May 1).
     * @method bool isKupalaNight() Ukraine: Kupala Night (Jul 6–7).
     * @method bool isVictoryDayOverNazism() Ukraine: Victory Day over Nazism (May 9).
     * @method bool isMlkJrDay() US: Martin Luther King Jr. Day (third Monday of January).
     * @method bool isIndependenceDay() US: Independence Day (Jul 4).
     * @method bool isMemorialDay() US: Memorial Day (last Monday of May).
     * @method bool isLaborDay() US: Labor Day (first Monday of September).
     * @method bool isVeteransDay() US: Veterans Day (Nov 11).
     * @method bool isAmericanThanksgiving() US: Thanksgiving (fourth Thursday of November).
     * @method bool isPresidentsDay() US: Presidents' Day (third Monday of February).
     * @method bool isColumbusDay() US: Columbus Day (second Monday of October).
     * @method bool isZambianIndependenceDay() Zambia: Independence Day (Oct 24).
     * @method bool isZambianLabourDay() Zambia: Labour Day (May 1).
     * @method bool isZambianYouthDay() Zambia: Youth Day (Mar 12).
     * @method bool isZambianWomensDay() Zambia: Women's Day (Mar 8 or following Monday).
     * @method bool isZambianAfricanUnityDay() Zambia: African Unity / Africa Day (May 25).
     * @method bool isZambianAfricaDay() Zambia: alias of African Unity Day.
     * @method bool isZambianHeroesDay() Zambia: Heroes' Day (first Monday of July).
     * @method bool isZambianUnityDay() Zambia: Unity Day (day after Heroes' Day).
     * @method bool isZambianFarmersDay() Zambia: Farmers' Day (first Monday of August).
     * @method bool isZambianNationalPrayerDay() Zambia: National Prayer Day (Oct 18).
     */
    class Carbon {}
}

namespace Illuminate\Database\Query {
    /**
     * @method \Illuminate\Database\Query\Builder whenFilled(mixed $value, callable $callback) Apply the callback only when $value is filled().
     * @method \Illuminate\Database\Query\Builder whereBetweenDates(string $column, mixed $startDate, mixed $endDate) Constrain a column between two dates.
     * @method \Illuminate\Database\Query\Builder orderByNullsLast(string $column, string $direction = 'asc') Order by the column with NULLs sorted last.
     * @method \Illuminate\Database\Query\Builder orderByNullsFirst(string $column, string $direction = 'asc') Order by the column with NULLs sorted first.
     * @method \Illuminate\Database\Query\Builder log(?string $channel = null) Log the compiled SQL + bindings to the given channel.
     */
    class Builder {}
}

namespace Illuminate\Database\Eloquent {
    /**
     * @method \Illuminate\Database\Eloquent\Builder whenFilled(mixed $value, callable $callback) Apply the callback only when $value is filled().
     * @method \Illuminate\Database\Eloquent\Builder whereBetweenDates(string $column, mixed $startDate, mixed $endDate) Constrain a column between two dates.
     * @method mixed existsOr(callable $callback) True when rows exist, otherwise the callback's result.
     * @method mixed doesntExistOr(callable $callback) True when no rows exist, otherwise the callback's result.
     */
    class Builder {}
}

namespace Illuminate\Database\Schema {
    /**
     * @method void addCommonFields() Add timestamps + soft deletes.
     * @method void addUserFields() Add nullable created_by / updated_by / deleted_by.
     * @method void addPublishingFields() Add is_published + nullable published_at.
     * @method void addStatusField(string $default = 'active') Add an indexed status string column.
     * @method void addSortingField(int $default = 0) Add an indexed sort_order integer column.
     * @method \Illuminate\Database\Schema\ColumnDefinition addSlugField(bool $nullable = false) Add a unique (optionally nullable) slug column.
     * @method void dropForeignIfExists(string $index) Drop a foreign key only when its column exists.
     * @method void dropColumnIfExists(string|array $columns) Drop column(s) only when they exist.
     * @method void addMetaFields() Add nullable meta_title / meta_description / meta_keywords.
     * @method void addSeoFields() Alias of addMetaFields().
     * @method void addLocationFields() Add nullable latitude / longitude decimals.
     * @method void addImageFields(string $prefix = '') Add (prefixed) image / image_alt / image_title columns.
     * @method void addPriceFields() Add price / sale_price / currency columns.
     * @method void addActivationFields() Add is_active + activated_at / deactivated_at.
     * @method void addExpiryFields() Add nullable starts_at / expires_at.
     * @method void addUuidPrimaryKey(string $column = 'id') Add a UUID primary key column.
     * @method void addNullableMorphs(string $name, ?string $indexName = null) Add nullable polymorphic *_type / *_id columns + index.
     */
    class Blueprint {}
}

namespace Illuminate\Http {
    /**
     * @method bool expectsJsonOrAjax() Whether the request wants JSON or is an AJAX call.
     * @method bool isBot() Whether the user agent looks like a crawler/bot.
     * @method bool isFromMobile() Whether the user agent looks like a mobile device.
     * @method bool hasFiles(array $keys) Whether every given key has an uploaded file.
     * @method bool hasValidFile(string $key) Whether the key holds a valid uploaded file.
     * @method string|null getReferer(?string $default = null) The Referer header, or a default.
     * @method bool isFromDomain(string $domain) Whether the Referer contains the given domain.
     * @method bool isJsonRequest() Whether the request is JSON or wants JSON.
     * @method array onlyFilled(array $keys) Only the given keys whose values are filled().
     * @method bool hasAny(array $keys) Whether the request has any of the given keys.
     * @method \Illuminate\Http\Request mergeIfMissing(array $values) Merge values for keys not already present.
     */
    class Request {}
}

namespace Illuminate\Database\Eloquent\Factories {
    /**
     * Factory mixin added via Factory::mixin(FactoryBuilderMixin).
     *
     * @method \Illuminate\Database\Eloquent\Factories\Factory withoutEvents() Flush the model's event listeners before continuing the factory chain.
     */
    class Factory {}
}
