<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

final class WithoutBaseUrlMacroTest extends TestCase
{
    public function test_it_strips_the_applications_own_base_url(): void
    {
        self::assertSame(
            '/storage/img/a.png',
            Str::withoutBaseUrl('https://example.test/storage/img/a.png'),
        );
    }

    public function test_a_relative_value_is_left_alone(): void
    {
        self::assertSame('/storage/img/a.png', Str::withoutBaseUrl('/storage/img/a.png'));
    }

    public function test_the_request_root_is_stripped_too(): void
    {
        // url('') is the current request root, which is not app.url behind a
        // proxy or on a secondary domain. Stripping only one leaves the other's
        // URLs absolute.
        self::assertSame('/a.png', Str::withoutBaseUrl(url('').'/a.png'));
    }

    public function test_another_hosts_url_is_left_alone(): void
    {
        self::assertSame(
            'https://cdn.other.test/a.png',
            Str::withoutBaseUrl('https://cdn.other.test/a.png'),
        );
    }

    public function test_an_explicit_base_handles_content_from_a_different_host(): void
    {
        // Which is the case url('') cannot cover: content imported from the
        // environment it was produced in.
        self::assertSame(
            '/a.png',
            Str::withoutBaseUrl('https://staging.example.test/a.png', 'https://staging.example.test'),
        );
    }

    public function test_a_trailing_slash_on_the_base_makes_no_difference(): void
    {
        self::assertSame(
            '/a.png',
            Str::withoutBaseUrl('https://other.test/a.png', 'https://other.test/'),
        );
    }

    public function test_every_occurrence_is_stripped(): void
    {
        self::assertSame(
            '<a href="/x">/y</a>',
            Str::withoutBaseUrl('<a href="https://example.test/x">https://example.test/y</a>'),
        );
    }

    public function test_an_empty_base_is_a_no_op(): void
    {
        self::assertSame('https://example.test/a', Str::withoutBaseUrl('https://example.test/a', ''));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://example.test');
    }
}
