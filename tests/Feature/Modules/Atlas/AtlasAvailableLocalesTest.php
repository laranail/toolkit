<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Modules\Atlas;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasService;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('atlas')]
class AtlasAvailableLocalesTest extends TestCase
{
    private string $langPath;

    /** @var array<int, string> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->langPath = resource_path('lang');
    }

    protected function tearDown(): void
    {
        // Remove only the directories this test created.
        foreach (array_reverse($this->created) as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }

        parent::tearDown();
    }

    private function makeLocaleDir(string $name): void
    {
        $dir = $this->langPath . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
            $this->created[] = $dir;
        }
    }

    private function service(): AtlasService
    {
        $service = $this->app->make(AtlasServiceInterface::class);
        $this->assertInstanceOf(AtlasService::class, $service);

        return $service;
    }

    public function test_available_locales_filters_by_lang_directories(): void
    {
        if (!is_dir($this->langPath)) {
            mkdir($this->langPath, 0o777, true);
            $this->created[] = $this->langPath;
        }

        // A registered exact locale, a bare ISO 639-1 locale, and an unknown one.
        $this->makeLocaleDir('en_US');
        $this->makeLocaleDir('ar');
        $this->makeLocaleDir('xx_NOPE');

        $available = $this->service()->availableLocales();

        $this->assertArrayHasKey('en_US', $available);
        $this->assertSame('en_US', $available['en_US']['locale']);
        $this->assertSame('English', $available['en_US']['name']);
        $this->assertSame('us', $available['en_US']['flag']);

        // 'ar' is matched by ISO 639-1 fallback to the registry entry.
        $this->assertArrayHasKey('ar', $available);
        $this->assertSame('العربية', $available['ar']['name']);

        // Unknown directory is ignored.
        $this->assertArrayNotHasKey('xx_NOPE', $available);
    }

    public function test_available_locales_skips_vendor_directory(): void
    {
        if (!is_dir($this->langPath)) {
            mkdir($this->langPath, 0o777, true);
            $this->created[] = $this->langPath;
        }

        $this->makeLocaleDir('vendor');

        $this->assertArrayNotHasKey('vendor', $this->service()->availableLocales());
    }
}
