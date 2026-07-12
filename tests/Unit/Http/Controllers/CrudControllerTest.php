<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Http\Controllers\CrudController;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CrudPost extends Model
{
    protected $table = 'crud_posts';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['title'];

    /**
     * @return HasMany<CrudComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CrudComment::class, 'post_id');
    }
}

class CrudComment extends Model
{
    protected $table = 'crud_comments';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['post_id', 'body'];
}

class CrudPostController extends CrudController
{
    public function __construct()
    {
        parent::__construct(new CrudPost());

        $this->relationships = ['comments'];
        $this->sortableFields = ['title'];
    }
}

class NonUniqueValidatedController extends CrudController
{
    public function __construct()
    {
        parent::__construct(new CrudPost());

        $this->validationRules = ['title' => 'required|max:255', 'tag' => ['nullable', 'string']];
    }
}

#[Group('http')]
class CrudControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crud_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });

        Schema::create('crud_comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('body');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('crud_comments');
        Schema::dropIfExists('crud_posts');

        parent::tearDown();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        $data = $response->getData(true);

        return is_array($data) ? $data : [];
    }

    public function test_get_all_records_sorts_by_whitelisted_column_descending(): void
    {
        CrudPost::insert([['title' => 'Banana'], ['title' => 'Apple'], ['title' => 'Cherry']]);

        $response = (new CrudPostController())->getAllRecords(
            Request::create('/', 'GET', ['sort_by' => 'title', 'sort_direction' => 'desc']),
        );

        $rows = (array) $this->decode($response)['data'];
        $titles = array_column($rows, 'title');

        self::assertSame(['Cherry', 'Banana', 'Apple'], $titles);
    }

    public function test_get_all_records_defaults_to_ascending_for_unknown_direction(): void
    {
        CrudPost::insert([['title' => 'Banana'], ['title' => 'Apple'], ['title' => 'Cherry']]);

        $response = (new CrudPostController())->getAllRecords(
            Request::create('/', 'GET', ['sort_by' => 'title', 'sort_direction' => 'sideways']),
        );

        $rows = (array) $this->decode($response)['data'];
        $titles = array_column($rows, 'title');

        self::assertSame(['Apple', 'Banana', 'Cherry'], $titles);
    }

    public function test_get_record_by_id_eager_loads_relationships(): void
    {
        $post = CrudPost::create(['title' => 'Hello']);
        $post->comments()->create(['body' => 'Nice one']);

        $response = (new CrudPostController())->getRecordById($post->getKey());

        $data = (array) $this->decode($response)['data'];
        $comments = (array) $data['comments'];

        self::assertSame('Hello', $data['title']);
        self::assertCount(1, $comments);
        self::assertSame('Nice one', ((array) $comments[0])['body']);
    }

    public function test_get_record_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new CrudPostController())->getRecordById(999);
    }

    public function test_store_record_loads_relationships_and_returns_201(): void
    {
        $response = (new CrudPostController())->storeRecord(
            Request::create('/', 'POST', ['title' => 'Fresh']),
        );

        self::assertSame(201, $response->getStatusCode());

        $json = $this->decode($response);
        $data = (array) $json['data'];

        self::assertSame('Record created successfully', $json['message']);
        self::assertSame('Fresh', $data['title']);
        // The (empty) relationship was eager-loaded onto the fresh record.
        self::assertSame([], $data['comments']);
    }

    public function test_update_record_loads_relationships(): void
    {
        $post = CrudPost::create(['title' => 'Old']);
        $post->comments()->create(['body' => 'existing']);

        $response = (new CrudPostController())->updateRecord(
            Request::create('/', 'PUT', ['title' => 'Updated']),
            $post->getKey(),
        );

        $json = $this->decode($response);
        $data = (array) $json['data'];
        $comments = (array) $data['comments'];

        self::assertSame('Record updated successfully', $json['message']);
        self::assertSame('Updated', $data['title']);
        self::assertCount(1, $comments);
    }

    public function test_delete_record_removes_the_row_and_returns_204(): void
    {
        $post = CrudPost::create(['title' => 'Doomed']);

        $response = (new CrudPostController())->deleteRecord($post->getKey());

        self::assertSame(204, $response->getStatusCode());
        self::assertNull(CrudPost::find($post->getKey()));
    }

    public function test_delete_record_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new CrudPostController())->deleteRecord(999);
    }

    public function test_update_skips_rules_without_unique_constraint(): void
    {
        // Exercises applyUniqueIgnore's skip branch: a plain string rule and an
        // array rule are both left untouched (no `unique:` segment to rewrite).
        $post = CrudPost::create(['title' => 'Before']);

        $response = (new NonUniqueValidatedController())->updateRecord(
            Request::create('/', 'PUT', ['title' => 'After', 'tag' => 'x']),
            $post->getKey(),
        );

        $data = (array) $this->decode($response)['data'];

        self::assertSame('After', $data['title']);
    }
}
