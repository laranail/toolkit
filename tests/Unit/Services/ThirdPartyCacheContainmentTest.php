<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Toolkit\Services\CacheService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * `clearThirdPartyCache()` is public and takes a config *key*, so whatever path
 * that key holds was handed straight to a recursive delete — no containment
 * check, no symlink check, no dry run.
 *
 * `filesystems.disks.local.root`, `view.compiled`, or simply a mistyped key
 * would each have emptied a directory the method has no business touching.
 * Every legitimate caller in the class (`purifier.cachePath`,
 * `debugbar.storage.path`) names somewhere inside `storage/`, so that is the
 * boundary these tests pin.
 */
final class ThirdPartyCacheContainmentTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/laranail-tpc-' . bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->sandbox . '/storage/framework/cache');
        File::put($this->sandbox . '/storage/framework/cache/blob.php', '<?php // cached');

        File::ensureDirectoryExists($this->sandbox . '/precious');
        File::put($this->sandbox . '/precious/keep.txt', 'do not delete me');

        $this->app->setBasePath($this->sandbox);
        $this->app->useStoragePath($this->sandbox . '/storage');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    private function service(): CacheService
    {
        return $this->app->make(CacheService::class);
    }

    #[Test]
    public function it_clears_a_cache_directory_inside_storage(): void
    {
        config()->set('some-package.cache_path', $this->sandbox . '/storage/framework/cache');

        $this->service()->clearThirdPartyCache('some-package.cache_path');

        self::assertFileDoesNotExist($this->sandbox . '/storage/framework/cache/blob.php');
        self::assertDirectoryExists(
            $this->sandbox . '/storage/framework/cache',
            'The directory itself should be preserved — only its contents go.',
        );
    }

    #[Test]
    public function it_refuses_a_path_outside_storage(): void
    {
        // The bug: any config key holding any path was cleared.
        config()->set('some-package.cache_path', $this->sandbox . '/precious');

        $this->service()->clearThirdPartyCache('some-package.cache_path');

        self::assertFileExists(
            $this->sandbox . '/precious/keep.txt',
            'A directory outside storage/ was emptied.',
        );
    }

    #[Test]
    public function it_refuses_the_storage_root_itself(): void
    {
        config()->set('some-package.cache_path', $this->sandbox . '/storage');

        $this->service()->clearThirdPartyCache('some-package.cache_path');

        self::assertFileExists($this->sandbox . '/storage/framework/cache/blob.php');
    }

    #[Test]
    public function it_refuses_a_symlink_even_when_it_points_inside_storage(): void
    {
        // Following one would empty somewhere the check never approved, and no
        // legitimate cache path needs to be a link.
        symlink($this->sandbox . '/precious', $this->sandbox . '/storage/linked');
        config()->set('some-package.cache_path', $this->sandbox . '/storage/linked');

        $this->service()->clearThirdPartyCache('some-package.cache_path');

        self::assertFileExists($this->sandbox . '/precious/keep.txt');
    }

    #[Test]
    public function an_unset_key_is_a_no_op_rather_than_an_error(): void
    {
        $this->service()->clearThirdPartyCache('nothing.configured.here');

        self::assertFileExists($this->sandbox . '/storage/framework/cache/blob.php');
    }

    #[Test]
    public function the_shipped_callers_still_work(): void
    {
        // purifier and debugbar are the two real callers, and both point
        // inside storage/ — the containment rule must not break them.
        File::ensureDirectoryExists($this->sandbox . '/storage/purifier');
        File::put($this->sandbox . '/storage/purifier/x.php', 'x');
        config()->set('purifier.cachePath', $this->sandbox . '/storage/purifier');

        $this->service()->clearPurifier();

        self::assertFileDoesNotExist($this->sandbox . '/storage/purifier/x.php');
    }
}
