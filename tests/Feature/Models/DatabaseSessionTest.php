<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Security\Session\DatabaseSession;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('support')]
class DatabaseSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The framework's session migration may already provide this table; use
        // our own minimal definition for a deterministic schema.
        Schema::dropIfExists('sessions');
        Schema::create('sessions', function ($table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable();
            $table->text('payload');
            $table->integer('last_activity');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sessions');

        parent::tearDown();
    }

    public function test_reads_a_session_row(): void
    {
        $payload = base64_encode(serialize(['_token' => 'abc', 'locale' => 'en']));

        DatabaseSession::query()->create([
            'id' => 'sess-1',
            'user_id' => 42,
            'payload' => $payload,
            'last_activity' => 1_700_000_000,
        ]);

        $session = DatabaseSession::query()->find('sess-1');

        $this->assertInstanceOf(DatabaseSession::class, $session);
        $this->assertSame('sess-1', $session->id);
        $this->assertFalse($session->incrementing);
        $this->assertSame('sessions', $session->getTable());
    }

    public function test_unserialized_payload_accessor_decodes_data(): void
    {
        $payload = base64_encode(serialize(['_token' => 'abc', 'locale' => 'en']));

        $session = DatabaseSession::query()->create([
            'id' => 'sess-2',
            'payload' => $payload,
            'last_activity' => 1_700_000_000,
        ]);

        $this->assertSame(['_token' => 'abc', 'locale' => 'en'], $session->unserialized_payload);
    }

    public function test_unserialized_payload_is_safe_on_garbage(): void
    {
        $session = DatabaseSession::query()->create([
            'id' => 'sess-3',
            'payload' => 'not-base64-or-serialized',
            'last_activity' => 1_700_000_000,
        ]);

        $this->assertSame([], $session->unserialized_payload);
    }

    public function test_last_activity_at_accessor_returns_carbon(): void
    {
        $session = DatabaseSession::query()->create([
            'id' => 'sess-4',
            'payload' => base64_encode(serialize([])),
            'last_activity' => 1_700_000_000,
        ]);

        $this->assertInstanceOf(Carbon::class, $session->last_activity_at);
        $this->assertSame(1_700_000_000, $session->last_activity_at->getTimestamp());
    }

    public function test_table_can_be_overridden(): void
    {
        $session = new DatabaseSession()->usingTable('custom_sessions');

        $this->assertSame('custom_sessions', $session->getTable());
    }
}
