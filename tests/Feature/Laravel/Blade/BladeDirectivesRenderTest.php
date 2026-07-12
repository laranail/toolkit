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

    public function test_addstyle_and_addscript_directives_emit_asset_tags(): void
    {
        $this->assertSame(
            '<link rel="stylesheet" href="/css/app.css">',
            Blade::render("@addstyle('/css/app.css')"),
        );
        $this->assertSame(
            '<script src="/js/app.js"></script>',
            Blade::render("@addscript('/js/app.js')"),
        );

        // No-argument block form wraps inline content.
        $this->assertSame(
            '<style>body{}</style>',
            str_replace(' ', '', Blade::render('@addstyle body{} @endaddstyle')),
        );
        $this->assertSame(
            "<script>alert('x')</script>",
            str_replace(' ', '', Blade::render("@addscript alert('x') @endaddscript")),
        );
    }

    public function test_inline_directive_wraps_by_extension(): void
    {
        $this->assertStringContainsString(
            '<style>',
            Blade::compileString("@inline('foo.css')"),
        );
        $this->assertStringContainsString(
            '<script>',
            Blade::compileString("@inline('foo.js')"),
        );
        // Compiles to an include of public_path(), not a raw echo of input.
        $this->assertStringContainsString(
            'include public_path',
            Blade::compileString("@inline('foo.css')"),
        );
    }

    public function test_nl2br_directive_inserts_breaks(): void
    {
        $out = Blade::render('@nl2br($text)', ['text' => "a\nb"]);

        $this->assertSame("a<br />\nb", $out);
    }

    public function test_data_attributes_directive_renders_attributes(): void
    {
        $out = Blade::render('@dataAttributes($attrs)', ['attrs' => ['id' => '7', 'role' => 'btn']]);

        $this->assertSame('data-id="7" data-role="btn"', $out);
    }

    public function test_haserror_block_shows_on_validation_error(): void
    {
        $this->assertStringContainsString('$errors->has', Blade::compileString("@haserror('name')x@endhaserror"));
    }

    public function test_selectedif_directive(): void
    {
        $this->assertSame('selected', Blade::render('@selectedif($v)', ['v' => true]));
        $this->assertSame('', Blade::render('@selectedif($v)', ['v' => false]));
    }

    public function test_inputvalue_directive_echoes_old_or_model_value(): void
    {
        $model = (object) ['title' => 'Hello'];

        $out = Blade::render("@inputvalue(\$m, 'title')", ['m' => $model]);

        $this->assertSame('Hello', $out);
    }

    public function test_returnifempty_directive_bails_on_empty(): void
    {
        $this->assertSame('', trim(Blade::render('@returnifempty($v) shown', ['v' => []])));
        $this->assertSame('shown', trim(Blade::render('@returnifempty($v) shown', ['v' => [1]])));
    }
}
