<?php

declare(strict_types=1);

/*
 * Render a Markdown coverage summary from a Clover report.
 *
 * CI appends the output to $GITHUB_STEP_SUMMARY so every run shows its
 * coverage on the run page, whether or not Codecov accepted the upload.
 * Plain PHP with ext-dom only, so it needs nothing beyond what the test
 * run already installed.
 *
 * Usage:
 *
 *     php scripts/coverage-summary.php [--clover=coverage.xml] [--lowest=10] [--min=N]
 *
 * The floor defaults to the `--min=N` in composer.json's `test:coverage`
 * script, the number contributors run locally; pass --min to override.
 * The total is executed / executable lines, the same figure Pest's
 * `--coverage --min` gates on.
 *
 * Writes to $GITHUB_STEP_SUMMARY when that is set (appending, as GitHub
 * expects), otherwise to stdout. Exits non-zero when the report is
 * missing or lists no files, so a run that measured nothing is never
 * rendered as a clean summary. It does not enforce the floor; Pest does.
 */

/** @return array{clover: string, lowest: int, min: ?float} */
function coverageSummaryOptions(array $argv): array
{
    $options = ['clover' => 'coverage.xml', 'lowest' => 10, 'min' => null];

    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--(clover|lowest|min)=(.+)$/', $arg, $m) !== 1) {
            fwrite(STDERR, "::error::Unknown argument {$arg}\n");
            exit(2);
        }
        $options[$m[1]] = match ($m[1]) {
            'clover' => $m[2],
            'lowest' => max(1, (int) $m[2]),
            'min'    => (float) $m[2],
        };
    }

    return $options;
}

function coverageSummaryComposerFloor(string $composerJson): ?float
{
    if (! is_file($composerJson)) {
        return null;
    }
    $composer = json_decode((string) file_get_contents($composerJson), true);
    $script = $composer['scripts']['test:coverage'] ?? null;
    $script = is_array($script) ? implode(' ', $script) : (string) $script;

    return preg_match('/--min[= ](\d+(?:\.\d+)?)/', $script, $m) === 1 ? (float) $m[1] : null;
}

/** @return list<array{path: string, statements: int, covered: int}> */
function coverageSummaryFiles(string $clover, string $root): array
{
    $dom = new DOMDocument;
    if (! @$dom->load($clover)) {
        fwrite(STDERR, "::error::{$clover} is not readable Clover XML\n");
        exit(1);
    }

    $files = [];
    foreach ((new DOMXPath($dom))->query('//file') as $file) {
        /** @var DOMElement $file */
        $metrics = null;
        foreach ($file->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'metrics') {
                $metrics = $child;
            }
        }
        if ($metrics === null) {
            continue;
        }
        $path = $file->getAttribute('name');
        if (str_starts_with($path, $root)) {
            $path = substr($path, strlen($root));
        }
        $files[] = [
            'path'       => $path,
            'statements' => (int) $metrics->getAttribute('statements'),
            'covered'    => (int) $metrics->getAttribute('coveredstatements'),
        ];
    }

    return $files;
}

/** @param array{statements: int, covered: int} $file */
function coverageSummaryPercent(array $file): float
{
    return $file['statements'] === 0 ? 100.0 : 100.0 * $file['covered'] / $file['statements'];
}

/** @param list<array{path: string, statements: int, covered: int}> $files */
function coverageSummaryRender(array $files, ?float $floor, int $lowest): string
{
    $statements = array_sum(array_column($files, 'statements'));
    $covered = array_sum(array_column($files, 'covered'));
    $total = coverageSummaryPercent(['statements' => $statements, 'covered' => $covered]);

    $out = ['## Coverage', ''];
    if ($floor === null) {
        $out[] = sprintf('**Total: %.2f%%** (%d/%d lines, no floor set)', $total, $covered, $statements);
    } else {
        $out[] = sprintf(
            '**Total: %.2f%%** (%d/%d lines) %s the enforced floor of **%s%%** (`pest --coverage --min`).',
            $total,
            $covered,
            $statements,
            $total >= $floor ? 'meets' : '**below**',
            rtrim(rtrim(sprintf('%.2f', $floor), '0'), '.'),
        );
    }

    $row = static fn (array $f): string => sprintf(
        '| `%s` | %d | %d | %.1f%% |',
        $f['path'],
        $f['statements'],
        $f['statements'] - $f['covered'],
        coverageSummaryPercent($f),
    );
    $header = ['| File | Lines | Miss | Cover |', '|---|---:|---:|---:|'];

    $byCoverage = $files;
    usort($byCoverage, static fn (array $a, array $b): int => [coverageSummaryPercent($a), $b['statements'] - $b['covered']]
        <=> [coverageSummaryPercent($b), $a['statements'] - $a['covered']]);

    $out = [...$out, '', sprintf('Measured across %d files. Lowest-covered:', count($files)), '', ...$header];
    foreach (array_slice($byCoverage, 0, $lowest) as $file) {
        $out[] = $row($file);
    }

    $byPath = $files;
    usort($byPath, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
    $out = [...$out, '', '<details><summary>Full report</summary>', '', ...$header];
    foreach ($byPath as $file) {
        $out[] = $row($file);
    }
    $out = [...$out, '', '</details>', ''];

    return implode("\n", $out);
}

$options = coverageSummaryOptions($argv);
$cwd = getcwd() ?: '.';

if (! is_file($options['clover'])) {
    fwrite(STDERR, "::error::{$options['clover']} not found; nothing to summarise\n");
    exit(1);
}

$files = coverageSummaryFiles($options['clover'], rtrim($cwd, '/') . '/');
if ($files === []) {
    fwrite(STDERR, "::error::{$options['clover']} lists no files; refusing to report 0 as a result\n");
    exit(1);
}

$floor = $options['min'] ?? coverageSummaryComposerFloor($cwd . '/composer.json');
$text = coverageSummaryRender($files, $floor, $options['lowest']);

$summary = getenv('GITHUB_STEP_SUMMARY');
if (is_string($summary) && $summary !== '') {
    file_put_contents($summary, $text, FILE_APPEND);
} else {
    echo $text;
}
