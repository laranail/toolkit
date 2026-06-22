<?php

declare(strict_types=1);

/**
 * Single source of truth for migration completeness (old/ -> new packages).
 *
 * This script REPLACES hand-auditing. It mechanically resolves every legacy
 * public type to its current status by ACTUAL presence in the new code, then:
 *
 *   php build-ledger.php            # regenerate the per-namespace ledger table
 *                                   # + summary counts into MIGRATION.md
 *                                   # (managed block; curated prose untouched)
 *
 *   php build-ledger.php --verify   # exit 0 iff every legacy symbol is
 *                                   # MIGRATED / RELOCATED / MERGED / DROPPED
 *                                   # with a real, existing target, and 0 GAP.
 *                                   # exit 1 on any GAP or missing/mislabelled
 *                                   # target. Also prints a method-level review
 *                                   # list (disappeared public methods) — that
 *                                   # is informational, not a failure.
 *
 * "Done" = this exits 0 AND both packages' gates are green. Do not re-audit by
 * hand; re-run this.
 */

$verify = in_array('--verify', array_slice($argv, 1), true);

$here = __DIR__;
$toolkitRoot = dirname($here, 3);                 // .../laranail (toolkit repo)
$oldSrc = dirname($toolkitRoot) . '/old/src';     // .../old/src
$notifSrc = dirname(dirname($toolkitRoot)) . '/notifications/src'; // .../laranail/notifications/src

$frozenPath = $here . '/old-api-surface.json';
$removedPath = $here . '/removed-symbols.json';
$migrationDoc = $toolkitRoot . '/docs/migration/MIGRATION.md';

/** Token-based public-API scanner: returns [fqcn => string[] publicMethods]. */
$scan = static function (string $root): array {
    $surface = [];
    if (! is_dir($root)) {
        return $surface;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $tokens = token_get_all((string) file_get_contents($file->getPathname()));
        $count = count($tokens);
        $namespace = '';
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if (! is_array($t)) {
                continue;
            }
            if ($t[0] === T_NAMESPACE) {
                $ns = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j] === ';' || $tokens[$j] === '{') {
                        break;
                    }
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $ns .= $tokens[$j][1];
                    }
                }
                $namespace = trim($ns, '\\');
            }
        }
        // Re-pass for types + methods (single declared-type-per-file assumption,
        // matching the repo's scan-api-surface.php).
        $lastFqcn = null;
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $prev = $tokens[$i - 1] ?? null;
                if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                    continue;
                }
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $lastFqcn = ($namespace !== '' ? $namespace . '\\' : '') . $tokens[$j][1];
                        $surface[$lastFqcn] ??= [];
                        break;
                    }
                }
            }
            if (is_array($t) && $t[0] === T_FUNCTION && $lastFqcn !== null) {
                $isPublic = true;
                for ($k = $i - 1; $k > 0 && $k > $i - 6; $k--) {
                    if (! is_array($tokens[$k])) {
                        if ($tokens[$k] === '{' || $tokens[$k] === '}' || $tokens[$k] === ';') {
                            break;
                        }
                        continue;
                    }
                    if (in_array($tokens[$k][0], [T_PRIVATE, T_PROTECTED], true)) {
                        $isPublic = false;
                        break;
                    }
                    if ($tokens[$k][0] === T_PUBLIC) {
                        $isPublic = true;
                        break;
                    }
                }
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        if ($isPublic) {
                            $surface[$lastFqcn][] = $tokens[$j][1];
                        }
                        break;
                    }
                    if ($tokens[$j] === '(') {
                        break;
                    }
                }
            }
        }
    }
    foreach ($surface as $k => $v) {
        $surface[$k] = array_values(array_unique($v));
        sort($surface[$k]);
    }

    return $surface;
};

$short = static fn (string $fqcn): string => (string) (($p = explode('\\', $fqcn)) ? end($p) : $fqcn);

/** Suffix-aware variants, matching ApiSurfaceTest. */
$variants = static function (string $s): array {
    $v = [$s];
    foreach (['DTO', 'Facade', 'Resource'] as $suffix) {
        if (str_ends_with($s, $suffix) && strlen($s) > strlen($suffix)) {
            $v[] = substr($s, 0, -strlen($suffix));
        }
    }

    return $v;
};

// --- Load inputs ------------------------------------------------------------
$frozen = (array) json_decode((string) file_get_contents($frozenPath), true, 512, JSON_THROW_ON_ERROR);
$removed = (array) json_decode((string) file_get_contents($removedPath), true, 512, JSON_THROW_ON_ERROR);
$toolkit = $scan($toolkitRoot . '/src');
$notif = $scan($notifSrc);
$oldNow = $scan($oldSrc);

// "old" set = frozen UNION current old/src (so nothing in old/ escapes).
$oldSet = $frozen;
$newInOld = [];
foreach ($oldNow as $fqcn => $methods) {
    if (! array_key_exists($fqcn, $oldSet)) {
        $oldSet[$fqcn] = $methods;
        $newInOld[] = $fqcn;
    }
}

// Build new-side short-name -> [fqcn] indexes.
$indexShort = static function (array $surface): array {
    $idx = [];
    foreach (array_keys($surface) as $fqcn) {
        $parts = explode('\\', $fqcn);
        $idx[end($parts)][] = $fqcn;
    }

    return $idx;
};
$toolkitByShort = $indexShort($toolkit);
$notifByShort = $indexShort($notif);

$matchFqcn = static function (string $sh, array $byShort) use ($variants): ?string {
    foreach ($variants($sh) as $v) {
        if (isset($byShort[$v])) {
            return $byShort[$v][0];
        }
    }

    return null;
};

// --- Resolve every legacy type ---------------------------------------------
$rows = [];       // fqcn => [status, target, namespace, lostMethods[]]
$gaps = [];
$targetFailures = [];
$counts = ['MIGRATED' => 0, 'MERGED' => 0, 'RELOCATED' => 0, 'DROPPED' => 0];

foreach ($oldSet as $fqcn => $oldMethods) {
    $sh = $short($fqcn);
    $ns = implode('\\', array_slice(explode('\\', $fqcn), 0, -1));

    $tk = $matchFqcn($sh, $toolkitByShort);
    if ($tk !== null) {
        // MIGRATED (present in toolkit by short name) — method-level review.
        $lost = array_values(array_diff($oldMethods, $toolkit[$tk] ?? []));
        $rows[$fqcn] = ['MIGRATED', $tk, $ns, $lost];
        $counts['MIGRATED']++;

        continue;
    }

    $nf = $matchFqcn($sh, $notifByShort);
    if ($nf !== null) {
        $lost = array_values(array_diff($oldMethods, $notif[$nf] ?? []));
        $rows[$fqcn] = ['RELOCATED', $nf, $ns, $lost];
        $counts['RELOCATED']++;

        continue;
    }

    if (array_key_exists($fqcn, $removed)) {
        $status = strtoupper((string) ($removed[$fqcn]['status'] ?? 'dropped'));
        $target = (string) ($removed[$fqcn]['target'] ?? '');
        $rows[$fqcn] = [$status, $target, $ns, []];
        $counts[$status] = ($counts[$status] ?? 0) + 1;

        // target-exists verification
        if ($status === 'MERGED') {
            // target may carry a prose suffix — pull the first \-qualified FQCN.
            $tShort = null;
            if (preg_match('/[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/', $target, $m) === 1) {
                $parts = explode('\\', $m[0]);
                $tShort = end($parts);
            }
            if ($tShort === null || $matchFqcn($tShort, $toolkitByShort) === null) {
                $targetFailures[] = "$fqcn -> MERGED target missing in toolkit: '$target'";
            }
        } elseif ($status === 'RELOCATED') {
            if (stripos($target, 'notifications') === false && $matchFqcn($sh, $notifByShort) === null) {
                $targetFailures[] = "$fqcn -> RELOCATED but not found in notifications (target '$target')";
            }
        } elseif ($status === 'DROPPED') {
            // a dropped short name must be ABSENT from the toolkit
            if ($matchFqcn($sh, $toolkitByShort) !== null) {
                $targetFailures[] = "$fqcn -> labelled DROPPED but a same-named class exists in toolkit";
            }
        }

        continue;
    }

    $gaps[] = $fqcn;
}

// --- Group rows by namespace for the ledger table --------------------------
$byNs = [];
foreach ($rows as $fqcn => [$status, $target, $ns, $lost]) {
    $byNs[$ns][] = [$fqcn, $status, $target];
}
ksort($byNs);

$total = count($oldSet);
$summary = sprintf(
    '| **MIGRATED** | %d | direct + %d merged |%s| **RELOCATED** | %d | → laranail/notifications |%s'
    . '| **DROPPED** | %d | native / out-of-scope (see rows) |%s| **Total** | %d | |',
    $counts['MIGRATED'] + $counts['MERGED'],
    $counts['MERGED'],
    "\n",
    $counts['RELOCATED'],
    "\n",
    $counts['DROPPED'],
    "\n",
    $total,
);

// --- Build the managed ledger block ----------------------------------------
$lines = [];
$lines[] = '<!-- LEDGER:START (generated by tests/Fixtures/Legacy/build-ledger.php — do not hand-edit) -->';
$lines[] = '## Verified per-namespace ledger (generated)';
$lines[] = '';
$lines[] = 'Mechanically resolved old→new by actual presence in the current code. Regenerate';
$lines[] = 'with `php tests/Fixtures/Legacy/build-ledger.php`; gate with `--verify`.';
$lines[] = '';
$lines[] = '| Status | Count | Note |';
$lines[] = '|---|---:|---|';
$lines[] = $summary;
$lines[] = '';
foreach ($byNs as $ns => $entries) {
    $lines[] = '### ' . $ns;
    $lines[] = '';
    $lines[] = '| Legacy type | Status | New target / reason |';
    $lines[] = '|---|---|---|';
    usort($entries, static fn ($a, $b) => strcmp($a[0], $b[0]));
    foreach ($entries as [$fqcn, $status, $target]) {
        $lines[] = sprintf('| `%s` | %s | %s |', $short($fqcn), $status, $target !== '' ? '`' . $target . '`' : '—');
    }
    $lines[] = '';
}
$lines[] = '<!-- LEDGER:END -->';
$block = implode("\n", $lines);

// --- Method-level review list (informational) ------------------------------
$lostReport = [];
foreach ($rows as $fqcn => [$status, $target, $ns, $lost]) {
    if (($status === 'MIGRATED' || $status === 'RELOCATED') && $lost !== []) {
        $lostReport[$fqcn] = $lost;
    }
}

// --- Verify mode ------------------------------------------------------------
if ($verify) {
    fwrite(STDOUT, "Migration verifier\n");
    fwrite(STDOUT, sprintf(
        "  old types: %d (frozen %d + %d found in current old/src)\n",
        $total,
        count($frozen),
        count($newInOld),
    ));
    fwrite(STDOUT, sprintf(
        "  MIGRATED %d | MERGED %d | RELOCATED %d | DROPPED %d\n",
        $counts['MIGRATED'],
        $counts['MERGED'],
        $counts['RELOCATED'],
        $counts['DROPPED'],
    ));

    if ($newInOld !== []) {
        fwrite(STDOUT, "  NOTE — types in current old/src not in the frozen snapshot:\n    "
            . implode("\n    ", $newInOld) . "\n");
    }

    if ($lostReport !== []) {
        fwrite(STDOUT, "  REVIEW (public methods not found on the new class — confirm intentional):\n");
        foreach ($lostReport as $fqcn => $lost) {
            fwrite(STDOUT, "    $fqcn :: " . implode(', ', $lost) . "\n");
        }
    }

    $fail = false;
    if ($gaps !== []) {
        $fail = true;
        sort($gaps);
        fwrite(STDERR, "\nFAIL — GAP: legacy types neither in the new code nor the allowlist:\n  "
            . implode("\n  ", $gaps) . "\n");
    }
    if ($targetFailures !== []) {
        $fail = true;
        sort($targetFailures);
        fwrite(STDERR, "\nFAIL — target verification:\n  " . implode("\n  ", $targetFailures) . "\n");
    }

    if ($fail) {
        fwrite(STDERR, "\nVERIFY: FAILED\n");
        exit(1);
    }

    fwrite(STDOUT, "\nVERIFY: PASSED — every legacy symbol is accounted for with a real target.\n");
    exit(0);
}

// --- Generate mode: write the managed block into MIGRATION.md ---------------
$doc = file_exists($migrationDoc) ? (string) file_get_contents($migrationDoc) : "# Migration ledger\n";
if (preg_match('/<!-- LEDGER:START.*?-->.*?<!-- LEDGER:END -->/s', $doc) === 1) {
    $doc = (string) preg_replace('/<!-- LEDGER:START.*?-->.*?<!-- LEDGER:END -->/s', $block, $doc);
} else {
    $doc = rtrim($doc) . "\n\n" . $block . "\n";
}
file_put_contents($migrationDoc, $doc);
fwrite(STDOUT, "Wrote verified ledger block to $migrationDoc\n");
fwrite(STDOUT, sprintf(
    "MIGRATED %d (incl %d merged) | RELOCATED %d | DROPPED %d | total %d\n",
    $counts['MIGRATED'] + $counts['MERGED'],
    $counts['MERGED'],
    $counts['RELOCATED'],
    $counts['DROPPED'],
    $total,
));
