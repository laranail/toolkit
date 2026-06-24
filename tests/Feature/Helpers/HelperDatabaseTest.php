<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Toolkit\Services\Contracts\DatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class HelperDatabaseTest extends TestCase
{
    private DatabaseServiceInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->app->make(DatabaseServiceInterface::class);

        Schema::create('widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
        });
    }

    public function test_can_connect_to_the_default_connection(): void
    {
        $this->assertTrue($this->db->canConnect());
    }

    public function test_can_connect_is_false_for_an_unknown_connection(): void
    {
        $this->assertFalse($this->db->canConnect('does-not-exist'));
    }

    public function test_table_and_column_existence_checks(): void
    {
        $this->assertTrue($this->db->tableExists('widgets'));
        $this->assertFalse($this->db->tableExists('ghosts'));
        $this->assertTrue($this->db->columnExists('widgets', 'label'));
        $this->assertFalse($this->db->columnExists('widgets', 'missing'));
    }

    public function test_existence_checks_are_exception_safe_on_a_bad_connection(): void
    {
        // An unknown connection makes Schema throw; the helpers must swallow it
        // and return false rather than propagate (covers the catch branches).
        $this->assertFalse($this->db->tableExists('widgets', 'does-not-exist'));
        $this->assertFalse($this->db->columnExists('widgets', 'label', 'does-not-exist'));
    }

    public function test_connection_names_lists_configured_connections(): void
    {
        $this->assertContains('testing', $this->db->connectionNames());
    }
}
