<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards against the pre-rename `LaraUtilX` / Omar Chouman authorship debris
 * creeping back into shipped files (the package was originally `LaraUtilX`).
 */
#[Group('security')]
class NoLegacyDebrisTest extends TestCase
{
    /** Precise debris tokens (NOT bare "omar" — that false-matches `fromArray`). */
    private const DEBRIS = [
        'omar.chouman',
        'omarchouman',
        'Omar Chouman',
        'lara-util-x',
        'laraUtilX',
        'LaraUtilX',
    ];

    public function test_no_legacy_authorship_debris_in_shipped_files(): void
    {
        $root = dirname(__DIR__, 3);
        $scan = ['src', 'config', 'docs', '.github', 'database', 'resources', 'stubs'];
        $rootFiles = ['composer.json', 'LICENSE', 'README.md', 'CONTRIBUTING.md', 'SECURITY.md', 'CODE_OF_CONDUCT.md'];

        $files = [];
        foreach ($rootFiles as $f) {
            if (is_file($root . '/' . $f)) {
                $files[] = $root . '/' . $f;
            }
        }
        foreach ($scan as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                /** @var \SplFileInfo $file */
                // The frozen legacy api-surface fixtures legitimately contain old FQCNs.
                if ($file->isFile() && !str_contains($file->getPathname(), '/Fixtures/Legacy/')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $offenders = [];
        foreach ($files as $path) {
            $contents = (string) file_get_contents($path);
            foreach (self::DEBRIS as $token) {
                if (stripos($contents, $token) !== false) {
                    $offenders[] = $token . ' in ' . $path;
                }
            }
        }

        $this->assertSame([], $offenders, "Legacy debris found:\n" . implode("\n", $offenders));
    }
}
