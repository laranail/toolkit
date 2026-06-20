<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Http\Controllers\CrudController;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CrudWidget extends Model
{
    protected $table = 'crud_widgets';

    public $timestamps = false;

    protected $fillable = ['name'];
}

class CrudWidgetController extends CrudController
{
    protected array $searchableFields = ['name'];

    protected array $sortableFields = ['name'];

    protected int $maxPerPage = 50;

    public function __construct()
    {
        parent::__construct(new CrudWidget());
    }
}

class ValidatedWidgetController extends CrudController
{
    protected array $validationRules = ['name' => 'required|unique:crud_widgets,name'];

    public function __construct()
    {
        parent::__construct(new CrudWidget());
    }
}

#[Group('security')]
class CrudControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crud_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('secret')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('crud_widgets');
        parent::tearDown();
    }

    public function test_store_does_not_mass_assign_fields_outside_fillable(): void
    {
        $controller = new CrudWidgetController();

        $response = $controller->storeRecord(
            Request::create('/', 'POST', ['name' => 'Acme', 'secret' => 'pwned']),
        );

        $id = $response->getData(true)['data']['id'];
        $widget = CrudWidget::findOrFail($id);

        $this->assertSame('Acme', $widget->name);
        $this->assertNull($widget->secret);
    }

    public function test_per_page_is_clamped_to_max(): void
    {
        CrudWidget::insert([['name' => 'a'], ['name' => 'b'], ['name' => 'c']]);

        $response = (new CrudWidgetController())->getAllRecords(
            Request::create('/', 'GET', ['per_page' => 999999]),
        );

        $this->assertSame(50, $response->getData(true)['meta']['per_page']);
    }

    public function test_like_wildcards_in_search_are_escaped(): void
    {
        CrudWidget::insert([['name' => 'Alpha'], ['name' => 'Beta']]);

        // A bare '%' must not match every row.
        $response = (new CrudWidgetController())->getAllRecords(
            Request::create('/', 'GET', ['search' => '%']),
        );

        $this->assertCount(0, $response->getData(true)['data']);
    }

    public function test_unwhitelisted_sort_by_is_ignored(): void
    {
        CrudWidget::insert([['name' => 'a'], ['name' => 'b']]);

        // An injection-style sort_by must be ignored, not executed.
        $response = (new CrudWidgetController())->getAllRecords(
            Request::create('/', 'GET', ['sort_by' => 'name); drop table crud_widgets;--']),
        );

        $this->assertCount(2, $response->getData(true)['data']);
        $this->assertTrue(Schema::hasTable('crud_widgets'));
    }

    public function test_update_unique_rule_ignores_the_current_record(): void
    {
        $controller = new ValidatedWidgetController();
        $created = $controller->storeRecord(Request::create('/', 'POST', ['name' => 'Acme']));
        $id = $created->getData(true)['data']['id'];

        // Updating the same record with its own name must not trip unique.
        $response = $controller->updateRecord(Request::create('/', 'PUT', ['name' => 'Acme']), $id);

        $this->assertSame('Acme', $response->getData(true)['data']['name']);
    }

    public function test_update_unique_rule_still_rejects_a_duplicate_of_another_record(): void
    {
        $controller = new ValidatedWidgetController();
        $first = $controller->storeRecord(Request::create('/', 'POST', ['name' => 'Acme']));
        $second = $controller->storeRecord(Request::create('/', 'POST', ['name' => 'Beta']));
        $secondId = $second->getData(true)['data']['id'];

        $this->expectException(ValidationException::class);

        $controller->updateRecord(Request::create('/', 'PUT', ['name' => 'Acme']), $secondId);
    }
}
