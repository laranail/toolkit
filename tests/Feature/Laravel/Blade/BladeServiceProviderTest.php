<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Laravel\Blade;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('blade')]
class BladeServiceProviderTest extends TestCase
{
    public function test_conditional_directives_are_registered(): void
    {
        $compiled = Blade::compileString('@istrue($flag)yes@endistrue');

        $this->assertStringContainsString('(bool) ($flag) === true', $compiled);
    }

    public function test_isnull_directive_compiles(): void
    {
        $this->assertStringContainsString('is_null($value)', Blade::compileString('@isnull($value)x@endisnull'));
        $this->assertStringContainsString('! is_null($value)', Blade::compileString('@isnotnull($value)x@endisnotnull'));
    }

    public function test_repeat_directive_renders_n_times(): void
    {
        $output = Blade::render('@repeat(3)*@endrepeat');

        $this->assertSame('***', $output);
    }

    public function test_icon_directive_emits_markup(): void
    {
        $output = Blade::render("@fa('home', 'fa-lg')");

        $this->assertSame('<i class="fa fa-home fa-lg"></i>', $output);
    }

    public function test_bootstrap_icon_directive(): void
    {
        $output = Blade::render("@bi('check', '')");

        $this->assertSame('<i class="bi bi-check"></i>', $output);
    }

    public function test_native_directives_are_not_overridden(): void
    {
        // @selected is native and must NOT be re-registered by the toolkit.
        $output = Blade::render('<option @selected(true)>X</option>');

        $this->assertStringContainsString('selected', $output);
    }
}
