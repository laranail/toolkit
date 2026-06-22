<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Toolkit\Helpers\DbHelper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class DbHelperTest extends TestCase
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
        $this->assertTrue(DbHelper::canConnect());
    }

    public function test_can_connect_is_false_for_an_unknown_connection(): void
    {
        $this->assertFalse(DbHelper::canConnect('does-not-exist'));
    }

    public function test_table_and_column_existence_checks(): void
    {
        $this->assertTrue(DbHelper::tableExists('widgets'));
        $this->assertFalse(DbHelper::tableExists('ghosts'));
        $this->assertTrue(DbHelper::columnExists('widgets', 'label'));
        $this->assertFalse(DbHelper::columnExists('widgets', 'missing'));
    }

    public function test_connection_names_lists_configured_connections(): void
    {
        $this->assertContains('testing', DbHelper::connectionNames());
    }
}
