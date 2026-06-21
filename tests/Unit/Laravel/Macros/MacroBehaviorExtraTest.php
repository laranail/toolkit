<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class MacroBehaviorExtraTest extends TestCase
{
    // ----- Str macros -----

    public function test_str_case_and_truncate_macros(): void
    {
        $this->assertSame('Hello World', Str::camelToTitle('helloWorld'));
        $this->assertSame('he...ld', Str::truncateMiddle('hello world', 7, '...'));
        $this->assertSame('short', Str::truncateMiddle('short', 50));
    }

    public function test_str_replace_many_and_accents_and_words(): void
    {
        $this->assertSame('1-2', Str::replaceMany('a-b', ['a' => '1', 'b' => '2']));
        $this->assertSame('cba', Str::reverseString('abc'));
        $this->assertSame(3, Str::countWords('one two three'));
        $this->assertFalse(Str::toBool('off'));
        $this->assertTrue(Str::toBool('on'));

        // removeAccents strips diacritics; the exact ASCII transliteration is
        // platform-dependent, so assert the accented characters are gone.
        $stripped = Str::removeAccents('café');
        $this->assertStringNotContainsString('é', $stripped);
        $this->assertStringContainsString('caf', $stripped);
    }

    // ----- Stringable macros -----

    public function test_stringable_macros_cover_all_helpers(): void
    {
        $this->assertSame('Hello World', (string) Str::of('hello_world')->snakeToTitle());
        $this->assertSame('Hello World', (string) Str::of('helloWorld')->camelToTitle());
        $this->assertSame('he...ld', (string) Str::of('hello world')->truncateMiddle(7));
        $this->assertSame('abc', (string) Str::of('a b c')->stripWhitespace());
        $this->assertSame('a b c', (string) Str::of("a   b\nc")->normalizeWhitespace());
        $this->assertTrue(Str::of('yes')->toBool());
        $this->assertSame('"x"', (string) Str::of('x')->wrapWith());
        $this->assertSame(2, Str::of('two words')->countWords());
        $this->assertStringNotContainsString('é', (string) Str::of('café')->removeAccents());
    }

    // ----- Collection macros -----

    public function test_collection_recursive_and_map_to_key(): void
    {
        $recursive = (new Collection(['a' => ['b' => 1]]))->recursive();
        $this->assertInstanceOf(Collection::class, $recursive->get('a'));

        $mapped = (new Collection(['x', 'y']))->mapToKey(fn (string $v, int $k): array => ["k{$k}", $v]);
        $this->assertSame(['k0' => 'x', 'k1' => 'y'], $mapped->all());
    }

    public function test_collection_filter_recursive_and_first_or_fail(): void
    {
        $filtered = (new Collection([1, 0, [2, 0]]))->filterRecursive();
        $this->assertSame(1, $filtered->get(0));
        $this->assertInstanceOf(Collection::class, $filtered->get(2));
        $this->assertSame([2], $filtered->get(2)->values()->all());

        $this->assertSame(2, (new Collection([1, 2, 3]))->firstOrFail(fn (int $n): bool => $n === 2));

        $this->expectException(RuntimeException::class);
        (new Collection([]))->firstOrFail();
    }

    public function test_collection_sum_average_and_csv(): void
    {
        $this->assertSame(10, (new Collection([[1, 2], [3, 4]]))->sumRecursive());
        $this->assertSame(2, (new Collection([1, 2, 3]))->averageBy(fn (int $n): int => $n));

        $csv = (new Collection([['a', 'b'], ['c', 'd']]))->toCsv();
        $this->assertSame(['"a","b"', '"c","d"'], $csv);
    }

    public function test_collection_rotate_right_and_tree(): void
    {
        $this->assertSame([], (new Collection([]))->rotateRight()->all());
        $this->assertSame([3, 1, 2], (new Collection([1, 2, 3]))->rotateRight()->values()->all());

        $rows = new Collection([
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
        ]);
        $tree = $rows->toTree();
        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree->first()['children']);
    }

    public function test_collection_insert_after_and_before(): void
    {
        // Found-key path: returns a collection that still contains the
        // original keys (numeric inserts re-key, hence assert membership).
        $after = (new Collection(['a' => 1, 'b' => 2]))->insertAfter('a', 'X');
        $this->assertInstanceOf(Collection::class, $after);
        $this->assertTrue($after->keys()->contains('b'));

        $before = (new Collection(['a' => 1, 'b' => 2]))->insertBefore('b', 'Y');
        $this->assertInstanceOf(Collection::class, $before);
        $this->assertTrue($before->keys()->contains('a'));

        // Missing-key path: append (after) / prepend (before) with the key.
        $missingAfter = (new Collection(['a' => 1]))->insertAfter('zzz', 'V');
        $this->assertSame('V', $missingAfter->get('zzz'));

        $missingBefore = (new Collection(['a' => 1]))->insertBefore('zzz', 'W');
        $this->assertSame('W', $missingBefore->first());
    }

    // ----- Arr macros -----

    public function test_arr_filter_and_map_macros(): void
    {
        $this->assertSame(['a' => 1], Arr::filterNulls(['a' => 1, 'b' => null]));
        $this->assertSame(['a' => 1], Arr::filterEmpty(['a' => 1, 'b' => 0, 'c' => '']));

        $mapped = Arr::mapKeys(['a' => 1], fn (string $k, int $v): string => $k . $v);
        $this->assertSame(['a1' => 1], $mapped);
    }

    public function test_arr_insert_and_remove_macros(): void
    {
        $this->assertSame(['a' => 1, 'x' => 9, 'b' => 2], Arr::insertAfter(['a' => 1, 'b' => 2], 'a', ['x' => 9]));
        $this->assertSame(['a' => 1, 'b' => 2, 'z' => 0], Arr::insertAfter(['a' => 1, 'b' => 2], 'missing', ['z' => 0]));

        $this->assertSame(['x' => 9, 'a' => 1, 'b' => 2], Arr::insertBefore(['a' => 1, 'b' => 2], 'a', ['x' => 9]));
        $this->assertSame(['z' => 0, 'a' => 1], Arr::insertBefore(['a' => 1], 'missing', ['z' => 0]));

        $this->assertSame([1, 3], Arr::removeValues([1, 2, 3, 4], [2, 4]));
        $this->assertSame(['a' => 1], Arr::renameKey(['a' => 1], 'absent', 'b'));
    }

    public function test_arr_average_median_group_unique_sort(): void
    {
        $this->assertSame(0, Arr::average([]));
        $this->assertSame(0, Arr::median([]));
        $this->assertSame(5, Arr::average([['v' => 4], ['v' => 6]], 'v'));
        $this->assertSame(4, Arr::median([['v' => 4], ['v' => 4], ['v' => 9]], 'v'));

        $grouped = Arr::groupByKey([['t' => 'a', 'n' => 1], ['t' => 'a', 'n' => 2], ['t' => 'b', 'n' => 3]], 't');
        $this->assertCount(2, $grouped['a']);

        $unique = Arr::uniqueBy([['id' => 1], ['id' => 1], ['id' => 2]], 'id');
        $this->assertCount(2, $unique);

        $sorted = Arr::sortByKeys(['b' => 2, 'a' => 1, 'c' => 3], ['a', 'b']);
        $this->assertSame(['a', 'b', 'c'], array_keys($sorted));
    }

    // ----- QueryBuilder macros -----

    public function test_query_builder_when_filled_and_between_dates(): void
    {
        Schema::create('macro_logs', static function (Blueprint $table): void {
            $table->id();
            $table->string('status')->nullable();
            $table->date('created_on');
        });

        DB::table('macro_logs')->insert([
            ['status' => 'active', 'created_on' => '2024-01-10'],
            ['status' => 'inactive', 'created_on' => '2024-06-10'],
        ]);

        $filtered = DB::table('macro_logs')
            ->whenFilled('active', fn ($q, $v) => $q->where('status', $v))
            ->whenFilled(null, fn ($q) => $q->whereRaw('1 = 0'))
            ->count();
        $this->assertSame(1, $filtered);

        $between = DB::table('macro_logs')
            ->whereBetweenDates('created_on', '2024-01-01', '2024-03-01')
            ->count();
        $this->assertSame(1, $between);

        Schema::dropIfExists('macro_logs');
    }

    public function test_query_builder_nulls_first_and_log(): void
    {
        Schema::create('macro_ranks', static function (Blueprint $table): void {
            $table->id();
            $table->integer('rank')->nullable();
        });

        DB::table('macro_ranks')->insert([['rank' => 2], ['rank' => null], ['rank' => 1]]);

        $ranks = DB::table('macro_ranks')->orderByNullsFirst('rank', 'desc')->pluck('rank')->all();
        $this->assertSame([null, 2, 1], $ranks);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('debug')->once()->with('Query Builder SQL', \Mockery::type('array'));

        $returned = DB::table('macro_ranks')->log();
        $this->assertNotNull($returned);

        Schema::dropIfExists('macro_ranks');
    }

    public function test_eloquent_builder_macros(): void
    {
        Schema::create('macro_things', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        MacroThing::query()->create(['name' => 'present']);

        $this->assertTrue(MacroThing::query()->where('name', 'present')->existsOr(fn (): string => 'no'));
        $this->assertSame('cb', MacroThing::query()->where('name', 'absent')->existsOr(fn (): string => 'cb'));

        $this->assertTrue(MacroThing::query()->where('name', 'absent')->doesntExistOr(fn (): string => 'no'));
        $this->assertSame('cb', MacroThing::query()->where('name', 'present')->doesntExistOr(fn (): string => 'cb'));

        $whenFilled = MacroThing::query()
            ->whenFilled('present', fn ($q, $v) => $q->where('name', $v))
            ->whenFilled(null, fn ($q) => $q)
            ->whereBetweenDates('id', 0, 999)
            ->count();
        $this->assertSame(1, $whenFilled);

        Schema::dropIfExists('macro_things');
    }

    // ----- Blueprint macros -----

    public function test_blueprint_macros_create_expected_columns(): void
    {
        Schema::create('macro_full', static function (Blueprint $table): void {
            $table->addUuidPrimaryKey('uuid');
            $table->addCommonFields();
            $table->addUserFields();
            $table->addSortingField();
            $table->addSlugField(true);
            $table->addSeoFields();
            $table->addLocationFields();
            $table->addImageFields('hero');
            $table->addPriceFields();
            $table->addActivationFields();
            $table->addExpiryFields();
            $table->addNullableMorphs('owner');
        });

        $expectedColumns = [
            'uuid', 'created_at', 'deleted_at', 'created_by', 'sort_order', 'slug',
            'meta_title', 'latitude', 'hero_image', 'price', 'is_active', 'expires_at',
            'owner_type', 'owner_id',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(Schema::hasColumn('macro_full', $column), "missing {$column}");
        }

        Schema::dropIfExists('macro_full');
    }

    public function test_blueprint_drop_macros_are_safe(): void
    {
        Schema::create('macro_drop', static function (Blueprint $table): void {
            $table->id();
            $table->string('removable');
        });

        Schema::table('macro_drop', static function (Blueprint $table): void {
            $table->dropColumnIfExists(['removable', 'never_existed']);
            $table->dropForeignIfExists('never_existed');
        });

        $this->assertFalse(Schema::hasColumn('macro_drop', 'removable'));

        Schema::dropIfExists('macro_drop');
    }

    // ----- Request macros -----

    public function test_request_detection_macros(): void
    {
        $mobile = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => 'iPhone Safari']);
        $this->assertTrue($mobile->isFromMobile());

        $desktop = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla Desktop']);
        $this->assertFalse($desktop->isFromMobile());

        $ajax = Request::create('/', 'GET', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertTrue($ajax->expectsJsonOrAjax());

        $json = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $this->assertTrue($json->isJsonRequest());
    }

    public function test_request_referer_and_domain_macros(): void
    {
        $request = Request::create('/', 'GET', server: ['HTTP_REFERER' => 'https://laravel.com/docs']);
        $this->assertSame('https://laravel.com/docs', $request->getReferer());
        $this->assertTrue($request->isFromDomain('laravel.com'));
        $this->assertFalse($request->isFromDomain('example.org'));

        $noReferer = Request::create('/', 'GET');
        $this->assertSame('default', $noReferer->getReferer('default'));
        $this->assertFalse($noReferer->isFromDomain('laravel.com'));
    }

    public function test_request_has_any_and_merge_if_missing(): void
    {
        $request = Request::create('/', 'GET', ['a' => 1]);

        $this->assertTrue($request->hasAny(['a', 'b']));
        $this->assertFalse($request->hasAny(['x', 'y']));

        $request->mergeIfMissing(['a' => 99, 'b' => 2]);
        $this->assertSame('1', (string) $request->input('a'));
        $this->assertSame(2, $request->input('b'));
    }

    public function test_request_file_macros(): void
    {
        $file = UploadedFile::fake()->create('doc.txt', 1);

        $request = Request::create('/', 'POST', files: ['document' => $file]);

        $this->assertTrue($request->hasFiles(['document']));
        $this->assertFalse($request->hasFiles(['document', 'missing']));
        $this->assertTrue($request->hasValidFile('document'));
        $this->assertFalse($request->hasValidFile('missing'));
    }
}

/**
 * Named fixture for Eloquent builder macro tests.
 */
class MacroThing extends Model
{
    protected $table = 'macro_things';

    public $timestamps = false;

    protected $guarded = [];
}
