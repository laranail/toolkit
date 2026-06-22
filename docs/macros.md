# Macros & Blade directives

All macros are registered eagerly by `MacroServiceProvider`, and all directives
by `BladeServiceProvider`. Only additions with **no native Laravel equivalent**
are included — native duplicates were intentionally dropped (see
[migration record](migration/dropped.md)).

## Str / Stringable macros

`kebabToTitle`, `snakeToTitle`, `camelToTitle`, `truncateMiddle($len = 50,
$middle = '...')`, `isEmail`, `stripWhitespace`, `normalizeWhitespace`, `toBool`,
`wrapWith($wrapper = '"')`, `replaceMany($replacements)` (Str only),
`reverseString`, `countWords`, `removeAccents`,
`readingMinutes($wordsPerMinute = 200)`, `highlightWords($words)`.

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
`tail($preserveKeys = false)`, `toPairs()`, `fromPairs()`, `ifEmpty($cb)`.

```php
collect($flatRows)->toTree('parent_id'); // nested children tree
collect($items)->prioritize(fn ($i) => $i->pinned);
collect([1, 1, 2, 3, 3])->chunkBy(fn ($n) => $n); // [[1,1],[2],[3,3]]
collect($rows)->forSelectBox('id', 'name');       // ['' => '', 5 => 'Apple', ...]
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
