<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\Auditable;

class AuditWidget extends Model
{
    use Auditable;

    protected $table = 'audit_widgets';

    public $timestamps = false;

    protected $fillable = ['name', 'secret'];

    protected $hidden = ['secret'];
}

#[Group('security')]
class AuditableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('secret')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_widgets');
        parent::tearDown();
    }

    public function test_create_audit_redacts_hidden_attributes(): void
    {
        AuditWidget::create(['name' => 'Acme', 'secret' => 'top-secret']);

        $audit = DB::table('model_audits')->where('event', 'created')->first();
        $newValues = json_decode($audit->new_values, true);

        $this->assertSame('Acme', $newValues['name']);
        $this->assertSame('[REDACTED]', $newValues['secret']);
        $this->assertStringNotContainsString('top-secret', $audit->new_values);
    }

    public function test_audit_failure_does_not_break_the_model_write(): void
    {
        Schema::drop('model_audits');

        // Creating the model must still succeed even though auditing fails.
        $widget = AuditWidget::create(['name' => 'Acme']);

        $this->assertTrue($widget->exists);
    }
}
