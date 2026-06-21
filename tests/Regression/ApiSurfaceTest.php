<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Regression;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Formal 0-unplanned-loss proof.
 *
 * Diffs the frozen public-API snapshot of the legacy monolith
 * (tests/Fixtures/Legacy/old-api-surface.json) against the current toolkit
 * surface. Every legacy type must be either:
 *   - MIGRATED — present in the toolkit (rename-aware: a trailing DTO/Facade/
 *     Resource suffix may have been dropped in the flat layout), or
 *   - listed in the removed-symbols allowlist (relocated to a sibling package
 *     or intentionally dropped — see docs/migration/MIGRATION.md).
 *
 * Any legacy symbol that is neither fails the test — that is an *unplanned* gap.
 */
#[Group('regression')]
class ApiSurfaceTest extends TestCase
{
    public function test_no_legacy_symbol_is_lost_without_being_recorded(): void
    {
        $legacy = $this->json('old-api-surface.json');
        $allowlist = $this->json('removed-symbols.json');
        $toolkit = $this->scanToolkit();

        $unplanned = [];
        foreach (array_keys($legacy) as $fqcn) {
            $short = $this->short($fqcn);

            if ($this->matched($short, $toolkit)) {
                continue; // migrated
            }
            if (array_key_exists($fqcn, $allowlist)) {
                continue; // relocated / dropped, on the record
            }

            $unplanned[] = $fqcn;
        }

        sort($unplanned);
        $this->assertSame(
            [],
            $unplanned,
            "Legacy symbols vanished without a MIGRATION.md / removed-symbols.json entry:\n"
            . implode("\n", $unplanned),
        );
    }

    public function test_allowlist_has_no_stale_entries(): void
    {
        // Every allowlisted symbol must genuinely be absent from the toolkit;
        // otherwise the allowlist is lying (a symbol was re-added but still
        // listed as removed).
        $allowlist = $this->json('removed-symbols.json');
        $toolkit = $this->scanToolkit();

        $stale = [];
        foreach (array_keys($allowlist) as $fqcn) {
            if ($this->matched($this->short($fqcn), $toolkit)) {
                $stale[] = $fqcn;
            }
        }

        sort($stale);
        $this->assertSame(
            [],
            $stale,
            "Allowlist lists these as removed, but they exist in the toolkit:\n" . implode("\n", $stale),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $name): array
    {
        $path = dirname(__DIR__) . '/Fixtures/Legacy/' . $name;

        return (array) json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function short(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }

    /**
     * @param array<string, true> $table
     */
    private function matched(string $short, array $table): bool
    {
        return array_any($this->variants($short), fn ($variant) => isset($table[$variant]));
    }

    /**
     * @return list<string>
     */
    private function variants(string $short): array
    {
        $variants = [$short];
        foreach (['DTO', 'Facade', 'Resource'] as $suffix) {
            if (str_ends_with($short, $suffix) && strlen($short) > strlen($suffix)) {
                $variants[] = substr($short, 0, -strlen($suffix));
            }
        }

        return $variants;
    }

    /**
     * Short type names declared in the current toolkit src/.
     *
     * @return array<string, true>
     */
    private function scanToolkit(): array
    {
        $names = [];
        $src = dirname(__DIR__, 2) . '/src';

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            $pattern = '/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m';
            preg_match_all($pattern, $contents, $matches);

            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        return $names;
    }
}
