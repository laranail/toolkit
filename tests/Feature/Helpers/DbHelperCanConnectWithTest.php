<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Helpers;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Helpers\DbHelper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class DbHelperCanConnectWithTest extends TestCase
{
    public function test_can_connect_with_a_valid_ad_hoc_config(): void
    {
        $this->assertTrue(DbHelper::canConnectWith([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]));
    }

    public function test_returns_false_for_an_unreachable_config(): void
    {
        $this->assertFalse(DbHelper::canConnectWith([
            'driver' => 'sqlite',
            'database' => '/nonexistent/dir/' . uniqid() . '/db.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]));
    }

    public function test_never_mutates_the_default_connection_or_leaks_temp_config(): void
    {
        $before = config('database.connections');
        $defaultBefore = config('database.default');

        DbHelper::canConnectWith([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // The throwaway connection is purged + unset; the default is untouched.
        $this->assertSame($before, config('database.connections'));
        $this->assertSame($defaultBefore, config('database.default'));

        // No leftover probe connection lingers in config.
        foreach (array_keys((array) config('database.connections')) as $name) {
            $this->assertStringStartsNotWith('laranail_probe_', (string) $name);
        }
    }
}
