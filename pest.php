<?php

declare(strict_types=1);

use Simtabi\Laranail\Toolkit\Providers\ToolkitServiceProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Bind the package Testbench TestCase to the Unit and Feature suites so
| Pest's functional tests boot the package the same way the class-based
| tests do.
|
*/

uses(TestCase::class)->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Get the package service providers.
 */
function getPackageServiceProviders(): array
{
    return [
        ToolkitServiceProvider::class,
    ];
}

/**
 * Get the package aliases.
 */
function getPackageAliases(): array
{
    // The fluent Toolkit facade is introduced in a later phase; no aliases yet.
    return [];
}
