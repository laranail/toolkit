<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class HelperDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
        });
    }

    public function test_can_connect_to_the_default_connection(): void
    {
        $this->assertTrue(Helper::canConnect());
    }

    public function test_can_connect_is_false_for_an_unknown_connection(): void
    {
        $this->assertFalse(Helper::canConnect('does-not-exist'));
    }

    public function test_table_and_column_existence_checks(): void
    {
        $this->assertTrue(Helper::tableExists('widgets'));
        $this->assertFalse(Helper::tableExists('ghosts'));
        $this->assertTrue(Helper::columnExists('widgets', 'label'));
        $this->assertFalse(Helper::columnExists('widgets', 'missing'));
    }

    public function test_existence_checks_are_exception_safe_on_a_bad_connection(): void
    {
        // An unknown connection makes Schema throw; the helpers must swallow it
        // and return false rather than propagate (covers the catch branches).
        $this->assertFalse(Helper::tableExists('widgets', 'does-not-exist'));
        $this->assertFalse(Helper::columnExists('widgets', 'label', 'does-not-exist'));
    }

    public function test_connection_names_lists_configured_connections(): void
    {
        $this->assertContains('testing', Helper::connectionNames());
    }
}
