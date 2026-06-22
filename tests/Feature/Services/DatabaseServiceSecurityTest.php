<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Simtabi\Laranail\Toolkit\Services\DatabaseService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class DatabaseServiceSecurityTest extends TestCase
{
    public function test_cleanup_is_confined_to_the_base_path_and_skips_symlinked_escapes(): void
    {
        $base = sys_get_temp_dir() . '/laranail-db-' . uniqid();
        $outside = sys_get_temp_dir() . '/laranail-out-' . uniqid();

        mkdir($base . '/storage/logs', 0777, true);
        mkdir($outside, 0777, true);

        $insideLog = $base . '/storage/logs/app.log';
        $outsideSecret = $outside . '/secret.log';
        file_put_contents($insideLog, 'inside');
        file_put_contents($outsideSecret, 'do-not-delete');

        // A symlink inside the swept dir pointing at a file outside the base.
        $escape = $base . '/storage/logs/escape.log';
        @symlink($outsideSecret, $escape);

        $service = new DatabaseService(new NullLogger(), $this->app->make('session.store'), $base);

        $service->clearLogFiles();

        // The genuine in-tree log is removed; the symlinked external file survives.
        $this->assertFileDoesNotExist($insideLog);
        $this->assertFileExists($outsideSecret);
        $this->assertSame('do-not-delete', file_get_contents($outsideSecret));

        // cleanup
        @unlink($escape);
        @unlink($outsideSecret);
        @rmdir($outside);
        @rmdir($base . '/storage/logs');
        @rmdir($base . '/storage');
        @rmdir($base);
    }

    public function test_missing_directories_are_skipped_safely(): void
    {
        $base = sys_get_temp_dir() . '/laranail-empty-' . uniqid();
        mkdir($base, 0777, true);

        $service = new DatabaseService(new NullLogger(), $this->app->make('session.store'), $base);

        // No storage/* dirs exist — must not throw and must report success.
        $this->assertTrue($service->clearLogFiles());

        @rmdir($base);
    }
}
