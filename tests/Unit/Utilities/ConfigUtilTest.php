<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Utilities;

use Illuminate\Support\Facades\Storage;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Utilities\ConfigUtil;

class ConfigUtilTest extends TestCase
{
    private ConfigUtil $config;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->config = new ConfigUtil(Storage::disk('local'), 'settings.json');
    }

    public function test_all_is_empty_before_anything_is_written(): void
    {
        $this->assertSame([], $this->config->all());
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertNull($this->config->get('missing'));
        $this->assertSame('fallback', $this->config->get('missing', 'fallback'));
    }

    public function test_set_then_get_round_trips_and_persists(): void
    {
        $this->config->set('mail.from', 'a@b.com');

        $this->assertSame('a@b.com', $this->config->get('mail.from'));
        Storage::disk('local')->assertExists('settings.json');

        // A fresh instance reads the same persisted store.
        $fresh = new ConfigUtil(Storage::disk('local'), 'settings.json');
        $this->assertSame('a@b.com', $fresh->get('mail.from'));
    }

    public function test_set_uses_dot_notation_nesting(): void
    {
        $this->config->set('feature.flags.beta', true);

        $this->assertTrue($this->config->get('feature.flags.beta'));
        $this->assertSame(['flags' => ['beta' => true]], $this->config->get('feature'));
    }

    public function test_has_reflects_presence(): void
    {
        $this->assertFalse($this->config->has('x'));
        $this->config->set('x', 1);
        $this->assertTrue($this->config->has('x'));
    }

    public function test_forget_removes_a_key(): void
    {
        $this->config->set('a', 1);
        $this->config->set('b', 2);

        $this->config->forget('a');

        $this->assertFalse($this->config->has('a'));
        $this->assertTrue($this->config->has('b'));
    }

    public function test_overwrites_an_existing_value(): void
    {
        $this->config->set('k', 'first');
        $this->config->set('k', 'second');

        $this->assertSame('second', $this->config->get('k'));
    }

    public function test_corrupt_store_degrades_to_empty(): void
    {
        Storage::disk('local')->put('settings.json', 'not-json');

        $this->assertSame([], $this->config->all());
        $this->assertNull($this->config->get('anything'));
    }
}
