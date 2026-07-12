<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php71\Rector\TryCatch\MultiExceptionCatchRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\Set\ValueObject\LevelSetList;

/**
 * Floor guard: pin to the PHP 8.4 set (the toolkit floor is ^8.4.1 via
 * laranail/console) so no newer-only syntax slips below the floor. Deliberately
 * NOT enabling the codeQuality / typeDeclarations / deadCode prepared sets — on
 * reviewed code they rewrite intentional, clearer constructs for no behavioural
 * gain (and conflict with Pint / PHPStan / the PSR-12 sniff).
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/tests/Fixtures',
        AddOverrideAttributeToOverriddenMethodsRector::class,
        // Typed `const array` trips the PSR-12 sniff tokenizer on this toolchain.
        AddTypeToConstRector::class,
        // Merging separate catches makes PHPStan flag the intentional first as dead.
        MultiExceptionCatchRector::class,
        // Keep the explicit `(new X())->y()` form — the parens-less PHP 8.4 syntax
        // is harder to read and we standardise on the wrapped form across the package.
        NewMethodCallWithoutParenthesesRector::class,
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
    ]);
