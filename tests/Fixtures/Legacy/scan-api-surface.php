<?php

declare(strict_types=1);

/**
 * Dependency-free public-API surface scanner (token-based, no autoload).
 *
 * Usage: php scan-api-surface.php <src-dir> > surface.json
 *
 * Emits a JSON map of every class/interface/trait/enum FQCN → its public
 * method names. Used to freeze the "before" migration state so relocations
 * and removals can be proven intentional (see plan: Prerequisite / Phase C).
 */
$root = $argv[1] ?? null;
if ($root === null || ! is_dir($root)) {
    fwrite(STDERR, "usage: php scan-api-surface.php <src-dir>\n");
    exit(1);
}

$surface = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $tokens = token_get_all((string) file_get_contents($file->getPathname()));
    $namespace = '';
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (! is_array($t)) {
            continue;
        }

        // namespace
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

        // type declaration
        if (in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            // skip anonymous class / ::class
            $prev = $tokens[$i - 1] ?? null;
            if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                continue;
            }
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $fqcn = ($namespace !== '' ? $namespace . '\\' : '') . $tokens[$j][1];
                    if (! isset($surface[$fqcn])) {
                        $surface[$fqcn] = [];
                    }
                    break;
                }
            }
        }
    }

    // public methods (heuristic: public function NAME, and constructor-promoted skipped)
    $current = null;
    $tokens2 = $tokens;
    $depth = 0;
    $classStack = [];
    // Re-derive FQCN list for this file to attach methods.
    // Simple pass: track the last declared type in the file's namespace.
    $lastFqcn = null;
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens2[$i];
        if (is_array($t) && in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            $prev = $tokens2[$i - 1] ?? null;
            if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                continue;
            }
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens2[$j]) && $tokens2[$j][0] === T_STRING) {
                    $lastFqcn = ($namespace !== '' ? $namespace . '\\' : '') . $tokens2[$j][1];
                    break;
                }
            }
        }
        if (is_array($t) && $t[0] === T_FUNCTION && $lastFqcn !== null) {
            // look back for visibility on the same statement
            $isPublic = true;
            for ($k = $i - 1; $k > 0 && $k > $i - 6; $k--) {
                if (! is_array($tokens2[$k])) {
                    if ($tokens2[$k] === '{' || $tokens2[$k] === '}' || $tokens2[$k] === ';') {
                        break;
                    }
                    continue;
                }
                if (in_array($tokens2[$k][0], [T_PRIVATE, T_PROTECTED], true)) {
                    $isPublic = false;
                    break;
                }
                if ($tokens2[$k][0] === T_PUBLIC) {
                    $isPublic = true;
                    break;
                }
            }
            // method name
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens2[$j]) && $tokens2[$j][0] === T_STRING) {
                    if ($isPublic) {
                        $surface[$lastFqcn][] = $tokens2[$j][1];
                    }
                    break;
                }
                if ($tokens2[$j] === '(') {
                    break; // closure
                }
            }
        }
    }
}

foreach ($surface as $k => $v) {
    $surface[$k] = array_values(array_unique($v));
    sort($surface[$k]);
}
ksort($surface);

echo json_encode($surface, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
