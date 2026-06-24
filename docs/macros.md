# Macros & Blade directives

All macros are registered eagerly by `MacroServiceProvider`, and all directives
by `BladeServiceProvider`. Only additions with **no native Laravel equivalent**
are included — native duplicates were intentionally dropped (see
[migration record](migration/dropped.md)).

## Str / Stringable macros

`kebabToTitle`, `snakeToTitle`, `camelToTitle`, `truncateMiddle($len = 50,
$middle = '...')`, `isEmail`, `stripWhitespace`, `normalizeWhitespace`, `toBool`,
`wrapWith($wrapper = '"')`, `replaceMany($replacements)` (Str only),
`matches($pattern)` (whole-string PCRE test → bool), `reverseString`,
`countWords`, `removeAccents`, `readingMinutes($wordsPerMinute = 200)`,
`highlightWords($words)`.

```php
Str::camelToTitle('helloWorld');          // "Hello World"
Str::truncateMiddle('a-very-long-name');  // "a-very-...name"
str('Café')->removeAccents();             // "Cafe"
Str::readingMinutes($article);            // 4
Str::highlightWords('the quick fox', 'quick'); // HtmlString: "the <mark>quick</mark> fox"
```

`highlightWords` is XSS-safe: the input text is `e()`-escaped, only matched terms
are wrapped in `<mark>…</mark>`, and the result is returned as an
`Illuminate\Support\HtmlString`.

## Arr macros

`filterNulls`, `filterEmpty`, `mapKeys($cb)`, `insertAfter($key, $insert)`,
`insertBefore($key, $insert)`, `removeValue($value)`, `removeValues($values)`,
`renameKey($old, $new)`, `renameKeys($changes)` (multi-rename from an
`[old => new]` map), `average($key = null)`, `median($key = null)`,
`groupByKey($key)`, `uniqueBy($key)`, `sortByKeys($keys)`.

```php
Arr::filterNulls(['a' => 1, 'b' => null]); // ['a' => 1]
Arr::average($rows, 'score');
```

## Collection macros

`transpose`, `recursive`, `mapToKey($cb)`, `filterRecursive($cb = null)`,
`firstOrFail($cb = null, $default = null)`, `sumRecursive($key = null)`,
`averageBy($cb)`, `toCsv($delimiter = ',', $enclosure = '"', $escape = '\\')`,
`prioritize($cb)`, `rotateLeft($count = 1)`, `rotateRight($count = 1)`,
`toTree($parentKey = 'parent_id', $childrenKey = 'children')`,
`insertAfter($key, $value)`, `insertBefore($key, $value)`,
`before($current, $strict = false)`, `insertAt($index, $item, $key = null)`,
`rotate($offset)` (signed), `firstOrPush($cb, $value, $instance = null)`,
`eachCons($size, $preserveKeys = false)` (overlapping windows),
`sliceBefore($cb, $preserveKeys = false)`, `chunkBy($cb, $preserveKeys = false)`,
`groupByModel($cb, $modelKey = 0, $itemsKey = 1)`,
`forSelectBox($key, $value, $addEmpty = true)`, `extract($keys)`,
`tail($preserveKeys = false)`, `toPairs()`, `fromPairs()`, `ifEmpty($cb)`,
`mapKeyValuePairs()` (map `{key, value}` rows into an associative collection),
`sortSearchResults($searchTerms, $column)` (relevance sort: exact +100,
starts-with +50, contains +25, else a `similar_text()` weight),
`pluckMany($keys)` (reduce each item to only the given keys),
`replaceInKeys($search, $replace)` (`str_replace` over every key, values kept).

```php
collect($flatRows)->toTree('parent_id'); // nested children tree
collect($items)->prioritize(fn ($i) => $i->pinned);
collect([1, 1, 2, 3, 3])->chunkBy(fn ($n) => $n); // [[1,1],[2],[3,3]]
collect($rows)->forSelectBox('id', 'name');       // ['' => '', 5 => 'Apple', ...]
collect($posts)->sortSearchResults('laravel api', 'title'); // most relevant first
```

## Query builder macros

On both the base query builder and Eloquent builder: `whenFilled($value, $cb)`,
`whereBetweenDates($column, $start, $end)`, `orderByNullsLast($column,
$direction = 'asc')` / `orderByNullsFirst(...)` (base builder), `log($channel =
null)` (base builder). Eloquent builder also adds `existsOr($cb)` and
`doesntExistOr($cb)`.

```php
Post::query()
    ->whenFilled($request->status, fn ($q, $v) => $q->where('status', $v))
    ->whereBetweenDates('published_at', $from, $to)
    ->get();
```

## Blueprint macros

Schema-building shortcuts: `addCommonFields()` (timestamps + soft deletes),
`addUserFields()` (created_by/updated_by/deleted_by), `addPublishingFields()`,
`addStatusField($default = 'active')`, `addSortingField($default = 0)`,
`addSlugField($nullable = false)`, `dropForeignIfExists($index)`,
`dropColumnIfExists($columns)`, `addMetaFields()`, `addSeoFields()` (alias of
meta), `addLocationFields()`, `addImageFields($prefix = '')`, `addPriceFields()`,
`addActivationFields()`, `addExpiryFields()`, `addUuidPrimaryKey($column = 'id')`,
`addNullableMorphs($name, $indexName = null)`.

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->addSlugField();
    $table->addPublishingFields();
    $table->addCommonFields();
});
```

## Factory macro

`withoutEvents()` — flush model event listeners for the factory chain.

## IDE autocomplete (macro stub)

Because every macro above is registered at runtime via `Macroable::macro()` /
`Factory::mixin()`, IDEs and static analysers cannot see them — you get no
autocomplete and no parameter hints. The package ships a committed stub at
[`ide-helper/_ide_helper_macros.php`](../ide-helper/_ide_helper_macros.php) that
re-opens each Illuminate namespace and declares every macro as a `@method` /
`@method static` tag on the real class. PhpStorm and VS Code (Intelephense)
**merge** those tags onto the framework class when indexing, restoring full
autocomplete and signatures.

The stub is a **static aid only** — it is deliberately **not** loaded at runtime:

- it is **not** listed in `composer.json` `autoload.files`;
- it lives **outside** the `src/` PSR-4 root, so it is never autoloaded;
- IDEs parse it; they never execute it, so the re-declared classes never collide
  with the framework's real ones.

> **Do not** add it to `composer autoload` and do not `require` it — that would
> redeclare core classes and fatal. If your IDE does not auto-index the project
> root, point it at the `ide-helper/` directory (PhpStorm:
> *Settings → PHP → Include Path*; or rely on barryvdh-style ide-helper tooling
> that discovers `_ide_helper*.php` files).

A drift test (`tests/Unit/Laravel/Macros/IdeHelperStubTest.php`) parses the stub's
`@method` names and asserts they match the registered macros **exactly in both
directions**, so the stub can never silently go stale: add a macro and the build
fails until the provider, the registration inventory, and the stub all agree.

## Blade directives

| Directive | Purpose |
|-----------|---------|
| `@istrue` / `@isfalse` / `@isnull` / `@isnotnull` (+ `@end…`) | Null-safe truthiness / null checks. |
| `@routeis('pattern')` / `@routeisnot('pattern')` (+ `@end…`) | `fnmatch` current-route-name checks. |
| `@activeifroute('pattern')` | Echo `active` when the current route name starts with the pattern. |
| `@instanceof($var, Class)` / `@typeof($var, 'type')` (+ `@end…`) | Type / `gettype` assertions. |
| `@repeat(n) … @endrepeat` | Loop N times. |
| `@fa` `@fas` `@far` `@fal` `@fab` `@fad` `@mdi` `@glyph` `@bi` | Icon shorthands (Font Awesome, Material Design, Glyphicons, Bootstrap Icons), each `('icon'[, 'extra-classes'])`. |
| `@window('name', $value)` | Expose a PHP value onto `window` in JS. |
| `@base64image($path)` | Inline an image file as a base64 data URI. |

```blade
@routeis('admin.*')
    <span class="badge">admin</span>
@endrouteis

<i class="@activeifroute('posts.*')">@fa('rss')</i>
```

[← Docs index](../README.md#documentation)
