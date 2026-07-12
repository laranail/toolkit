<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Log\AbstractLogger;
use Simtabi\Laranail\Toolkit\Services\ModelService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Stringable;

class ModelServiceUser extends Model
{
    protected $table = 'model_service_users';

    public $timestamps = false;

    protected $guarded = [];
}

class ModelServiceIntUser extends Model
{
    protected $table = 'model_service_users';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['username' => 'integer'];
}

class ModelServiceObserver {}

class ModelServiceTest extends TestCase
{
    private ModelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('model_service_users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
        });

        $this->service = $this->app->make(ModelService::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('model_service_users');

        parent::tearDown();
    }

    public function test_eloquent2selectbox_builds_a_placeholder_prefixed_map(): void
    {
        ModelServiceUser::create(['first_name' => 'Ada', 'username' => 'ada']);
        ModelServiceUser::create(['first_name' => 'Linus', 'username' => 'linus']);

        $box = $this->service->eloquent2selectbox(ModelServiceUser::all(), 'username', 'id', 'Pick one');

        $this->assertSame('Pick one', $box['']);
        $this->assertContains('ada', $box);
        $this->assertContains('linus', $box);
    }

    public function test_eloquent2selectbox_returns_empty_marker_for_empty_collection(): void
    {
        $box = $this->service->eloquent2selectbox(ModelServiceUser::all(), emptyDataText: 'Nothing here');

        $this->assertSame(['' => 'Nothing here'], $box);
    }

    public function test_formable_users_list_prefers_username_then_email(): void
    {
        ModelServiceUser::create(['username' => 'ada', 'email' => 'ada@example.com']);
        ModelServiceUser::create(['username' => null, 'email' => 'NO-NAME@EXAMPLE.COM']);

        $list = $this->service->getFormableUsersList(new ModelServiceUser());

        $this->assertSame('Ada', $list[1]);
        $this->assertSame('no-name@example.com', $list[2]);
    }

    public function test_get_users_from_model_concatenates_names(): void
    {
        ModelServiceUser::create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

        $users = $this->service->getUsersFromModel(new ModelServiceUser());

        $this->assertContains('Grace Hopper', $users);
    }

    public function test_concat_name_quotes_identifiers_via_grammar(): void
    {
        $expression = $this->service->concatName('model_service_users');
        $sql = $expression->getValue(DB::connection()->getQueryGrammar());

        // sqlite grammar wraps identifiers in double quotes.
        $this->assertStringContainsString('"model_service_users"."first_name"', $sql);
        $this->assertStringContainsString('"model_service_users"."last_name"', $sql);
    }

    public function test_sort_item_with_children_annotates_depth(): void
    {
        $a = (object) ['id' => 1, 'parent_id' => null];
        $b = (object) ['id' => 2, 'parent_id' => 1];
        $c = (object) ['id' => 3, 'parent_id' => 2];

        $sorted = $this->service->sortItemWithChildren([$a, $b, $c]);

        $this->assertSame([1, 2, 3], array_map(static fn ($o): int => $o->id, $sorted));
        $this->assertSame([0, 1, 2], array_map(static fn ($o): int => $o->depth, $sorted));
    }

    public function test_get_model_item_reads_by_dot_path_with_default(): void
    {
        $user = new ModelServiceUser();
        $user->forceFill(['first_name' => 'Jane', 'email' => 'jane@example.com']);

        $this->assertSame('Jane', $this->service->getModelItem($user, 'first_name'));
        $this->assertSame('fallback', $this->service->getModelItem($user, 'missing', 'fallback'));
        $this->assertNull($this->service->getModelItem($user, 'missing'));
    }

    public function test_formable_users_list_is_empty_for_a_table_with_no_rows(): void
    {
        $this->assertSame([], $this->service->getFormableUsersList(new ModelServiceUser()));
    }

    public function test_formable_users_list_stringifies_a_non_string_username(): void
    {
        ModelServiceUser::create(['username' => '42']);

        // The cast model returns the username as an int, exercising the numeric
        // branch of the internal string coercion.
        $list = $this->service->getFormableUsersList(new ModelServiceIntUser());

        $this->assertSame('42', $list[1]);
    }

    public function test_get_users_from_model_returns_a_plain_list_when_not_keyed(): void
    {
        ModelServiceUser::create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

        $users = $this->service->getUsersFromModel(new ModelServiceUser(), keyed: false);

        $this->assertArrayHasKey('name', $users[0]);
        $this->assertArrayHasKey('id', $users[0]);
        $this->assertSame('Grace Hopper', $users[0]['name']);
    }

    public function test_eloquent2selectbox_omits_the_placeholder_when_null(): void
    {
        ModelServiceUser::create(['first_name' => 'Ada', 'username' => 'ada']);

        $box = $this->service->eloquent2selectbox(ModelServiceUser::all(), 'username', 'id', null);

        $this->assertArrayNotHasKey('', $box);
        $this->assertContains('ada', $box);
    }

    public function test_register_model_observer_registers_and_logs_when_both_classes_exist(): void
    {
        $logger = new class() extends AbstractLogger
        {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $service = new ModelService($logger);

        $service->registerModelObserver(ModelServiceUser::class, ModelServiceObserver::class);

        $this->assertCount(1, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('Model observer registered', $logger->records[0]['message']);
        $this->assertSame(ModelServiceUser::class, $logger->records[0]['context']['model']);
        $this->assertSame(ModelServiceObserver::class, $logger->records[0]['context']['observer']);
    }

    public function test_register_model_observer_is_a_noop_when_a_class_is_missing(): void
    {
        $logger = new class() extends AbstractLogger
        {
            /** @var list<mixed> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = $message;
            }
        };

        $service = new ModelService($logger);

        $service->registerModelObserver('App\\Does\\Not\\Exist', ModelServiceObserver::class);

        $this->assertSame([], $logger->records);
    }
}
