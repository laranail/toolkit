<?php

declare(strict_types=1);

use Simtabi\Laranail\Toolkit\Commands\MakeCrud;

// `--per-page=` arrives as '' rather than null, so the `??` default never fired
// and `(int) ''` produced 0. That 0 was interpolated into the GENERATED
// controller as `paginate(0)` -- the defect shipped in scaffolded code rather
// than failing in this command, which is why nothing here caught it.
//
// Written as line comments, not a docblock: Pint's no_blank_lines_after_phpdoc
// and PSR12's FileHeader.SpacingAfterDocblockBlock demand opposite things of a
// docblock sitting directly above the first statement, and this repo gates on
// both. A line comment is outside the argument.
it('resolves per-page without a null-only default', function (): void {
    $source = (string) file_get_contents((string) (new ReflectionClass(MakeCrud::class))->getFileName());

    expect($source)->not->toContain("(int) (\$this->option('per-page') ?? 15)")
        ->and($source)->toContain('is_numeric($perPageOption)');
});

it('falls back to the documented default for an empty or non-numeric value', function (): void {
    // The behaviour the fix encodes, exercised directly rather than through the
    // generator, which needs a full scaffold to run.
    $resolve = (static fn (mixed $raw): int => is_numeric($raw) ? max(1, (int) $raw) : 15);

    expect($resolve(''))->toBe(15)
        ->and($resolve('abc'))->toBe(15)
        ->and($resolve(null))->toBe(15)
        ->and($resolve('25'))->toBe(25)
        ->and($resolve('0'))->toBe(1, 'a per-page of 0 would generate paginate(0)');
});
