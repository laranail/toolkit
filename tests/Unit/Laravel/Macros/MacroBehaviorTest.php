<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class MacroBehaviorTest extends TestCase
{
    public function test_str_helpers_behave(): void
    {
        $this->assertSame('Hello World', Str::kebabToTitle('hello-world'));
        $this->assertSame('Hello World', Str::snakeToTitle('hello_world'));
        $this->assertTrue(Str::isEmail('user@example.com'));
        $this->assertFalse(Str::isEmail('nope'));
        $this->assertSame('a b c', Str::normalizeWhitespace("a   b\nc"));
        $this->assertSame('abc', Str::stripWhitespace("a b\tc"));
        $this->assertTrue(Str::toBool('yes'));
        $this->assertFalse(Str::toBool('no'));
        $this->assertSame('"x"', Str::wrapWith('x'));
        $this->assertSame(2, Str::countWords('hello world'));
    }

    public function test_stringable_macro_chains(): void
    {
        $this->assertInstanceOf(Stringable::class, Str::of('hello-world')->kebabToTitle());
        $this->assertSame('Hello World', (string) Str::of('hello-world')->kebabToTitle());
        $this->assertSame('cba', (string) Str::of('abc')->reverseString());
        $this->assertTrue(Str::of('user@example.com')->isEmail());
    }

    public function test_collection_prioritize_moves_matches_to_front(): void
    {
        $result = new Collection([1, 2, 3, 4])
            ->prioritize(static fn (int $n): bool => $n % 2 === 0)
            ->values()
            ->all();

        $this->assertSame([2, 4, 1, 3], $result);
    }

    public function test_collection_transpose_swaps_rows_and_columns(): void
    {
        $result = new Collection([[1, 2], [3, 4]])->transpose()->all();

        $this->assertSame([[1, 3], [2, 4]], $result);
    }

    public function test_collection_rotate_left_handles_empty(): void
    {
        $this->assertSame([], new Collection([])->rotateLeft()->all());
        $this->assertSame([2, 3, 1], new Collection([1, 2, 3])->rotateLeft()->values()->all());
    }

    public function test_arr_remove_value_uses_value_membership(): void
    {
        // Regression: legacy bug used key-based Arr::has on a value.
        $this->assertSame([1, 3], Arr::removeValue([1, 2, 3, 2], 2));
    }

    public function test_arr_average_and_median(): void
    {
        $this->assertSame(2.5, Arr::average([1, 2, 3, 4]));
        $this->assertSame(2, Arr::median([3, 1, 2]));
        $this->assertSame(2.5, Arr::median([1, 2, 3, 4]));
    }

    public function test_arr_rename_key_keeps_order(): void
    {
        $this->assertSame(['a' => 1, 'c' => 2], Arr::renameKey(['a' => 1, 'b' => 2], 'b', 'c'));
    }

    public function test_query_builder_order_by_nulls_last_against_sqlite(): void
    {
        Schema::create('macro_widgets', static function (Blueprint $table): void {
            $table->id();
            $table->integer('rank')->nullable();
        });

        DB::table('macro_widgets')->insert([
            ['rank' => 2],
            ['rank' => null],
            ['rank' => 1],
        ]);

        $ranks = DB::table('macro_widgets')
            ->orderByNullsLast('rank')
            ->pluck('rank')
            ->all();

        $this->assertSame([1, 2, null], $ranks);

        Schema::dropIfExists('macro_widgets');
    }

    public function test_eloquent_exists_or_runs_callback_when_empty(): void
    {
        Schema::create('macro_gadgets', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        $fallback = MacroGadget::query()->where('name', 'missing')->existsOr(static fn (): string => 'fallback');

        $this->assertSame('fallback', $fallback);

        Schema::dropIfExists('macro_gadgets');
    }

    public function test_blueprint_column_group_macros_apply(): void
    {
        Schema::create('macro_articles', static function (Blueprint $table): void {
            $table->id();
            $table->addStatusField();
            $table->addPublishingFields();
            $table->addMetaFields();
        });

        $this->assertTrue(Schema::hasColumn('macro_articles', 'status'));
        $this->assertTrue(Schema::hasColumn('macro_articles', 'is_published'));
        $this->assertTrue(Schema::hasColumn('macro_articles', 'meta_title'));

        Schema::dropIfExists('macro_articles');
    }

    public function test_request_is_bot_detects_known_agents(): void
    {
        $request = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => 'Googlebot/2.1']);
        $this->assertTrue($request->isBot());

        $human = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh)']);
        $this->assertFalse($human->isBot());
    }

    public function test_request_only_filled_strips_blanks(): void
    {
        $request = Request::create('/', 'GET', ['a' => 'x', 'b' => '', 'c' => null]);

        $this->assertSame(['a' => 'x'], $request->onlyFilled(['a', 'b', 'c']));
    }
}

/**
 * Named fixture model for the Eloquent-builder macro tests (no anonymous
 * classes — keeps phpcs/Pint happy and gives the table a stable name).
 */
class MacroGadget extends Model
{
    protected $table = 'macro_gadgets';

    public $timestamps = false;

    protected $guarded = [];
}
