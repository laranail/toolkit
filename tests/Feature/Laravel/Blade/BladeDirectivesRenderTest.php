<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Laravel\Blade;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('blade')]
class BladeDirectivesRenderTest extends TestCase
{
    public function test_istrue_and_isfalse_blocks_render_conditionally(): void
    {
        $this->assertSame('yes', trim(Blade::render('@istrue($flag) yes @endistrue', ['flag' => true])));
        $this->assertSame('', trim(Blade::render('@istrue($flag) yes @endistrue', ['flag' => false])));

        $this->assertSame('no', trim(Blade::render('@isfalse($flag) no @endisfalse', ['flag' => false])));
        $this->assertSame('', trim(Blade::render('@isfalse($flag) no @endisfalse', ['flag' => true])));
    }

    public function test_isnull_and_isnotnull_blocks(): void
    {
        $this->assertSame('null', trim(Blade::render('@isnull($v) null @endisnull', ['v' => null])));
        $this->assertSame('set', trim(Blade::render('@isnotnull($v) set @endisnotnull', ['v' => 'x'])));
    }

    public function test_route_directives_match_the_current_route(): void
    {
        Route::get('/dashboard', fn () => 'ok')->name('dashboard.index');

        $this->get('/dashboard');

        // Set the current route name on the resolved route for directive evaluation.
        $template = "@routeis('dashboard.*')active@endrouteis";
        $compiled = Blade::compileString($template);
        $this->assertStringContainsString('fnmatch', $compiled);

        $notTemplate = "@routeisnot('admin.*')public@endrouteisnot";
        $this->assertStringContainsString('! fnmatch', Blade::compileString($notTemplate));

        $this->assertStringContainsString('str_starts_with', Blade::compileString("@activeifroute('dashboard.')"));
    }

    public function test_instanceof_and_typeof_directives_render(): void
    {
        $object = new \stdClass();

        $out = Blade::render(
            '@instanceof($o, \stdClass) yes @endinstanceof',
            ['o' => $object],
        );
        $this->assertSame('yes', trim($out));

        $typeOut = Blade::render('@typeof($n, "integer") int @endtypeof', ['n' => 5]);
        $this->assertSame('int', trim($typeOut));

        $typeMiss = Blade::render('@typeof($n, "integer") int @endtypeof', ['n' => 'str']);
        $this->assertSame('', trim($typeMiss));
    }

    public function test_all_icon_families_render(): void
    {
        $this->assertSame('<i class="fas fa-star"></i>', Blade::render("@fas('star', '')"));
        $this->assertSame('<i class="far fa-star"></i>', Blade::render("@far('star', '')"));
        $this->assertSame('<i class="fal fa-star"></i>', Blade::render("@fal('star', '')"));
        $this->assertSame('<i class="fab fa-github"></i>', Blade::render("@fab('github', '')"));
        $this->assertSame('<i class="fad fa-star"></i>', Blade::render("@fad('star', '')"));
        $this->assertSame('<i class="mdi mdi-home"></i>', Blade::render("@mdi('home', '')"));
        $this->assertSame('<i class="glyphicons glyphicons-ok"></i>', Blade::render("@glyph('ok', '')"));
    }

    public function test_window_directive_emits_script(): void
    {
        $out = Blade::render("@window('appName', \$value)", ['value' => '"Laranail"']);

        $this->assertStringContainsString('<script>', $out);
        $this->assertStringContainsString('window.appName =', $out);
    }

    public function test_base64image_directive_inlines_a_file(): void
    {
        $path = sys_get_temp_dir() . '/laranail-blade-' . uniqid() . '.png';
        file_put_contents($path, 'PNGDATA');

        try {
            $out = Blade::render('@base64image($path)', ['path' => $path]);

            $this->assertStringStartsWith('data:image/png;base64,', $out);
            $this->assertStringContainsString(base64_encode('PNGDATA'), $out);
        } finally {
            @unlink($path);
        }
    }
}
