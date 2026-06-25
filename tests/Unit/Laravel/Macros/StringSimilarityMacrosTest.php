<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class StringSimilarityMacrosTest extends TestCase
{
    public function test_levenshtein_distance(): void
    {
        $this->assertSame(3, Str::levenshtein('kitten', 'sitting'));
        $this->assertSame(0, Str::levenshtein('same', 'same'));
        $this->assertSame(3, Str::of('kitten')->levenshtein('sitting'));
    }

    public function test_similar_text_percentage(): void
    {
        $this->assertSame(100.0, Str::similarText('foo', 'foo'));
        $this->assertSame(0.0, Str::similarText('abc', 'xyz'));
        $this->assertGreaterThan(50.0, Str::similarText('World', 'word'));
        $this->assertSame(100.0, Str::of('foo')->similarText('foo'));
    }

    public function test_jaro_winkler_similarity(): void
    {
        $this->assertSame(1.0, Str::jaroWinkler('identical', 'identical'));
        $this->assertSame(0.0, Str::jaroWinkler('', 'nonempty'));
        $this->assertEqualsWithDelta(0.961, Str::jaroWinkler('MARTHA', 'MARHTA'), 0.001);
        $this->assertEqualsWithDelta(0.961, Str::of('MARTHA')->jaroWinkler('MARHTA'), 0.001);
    }

    public function test_closest_match(): void
    {
        $this->assertSame('apple', Str::closest('appel', ['grape', 'apple', 'apply']));
        $this->assertNull(Str::closest('anything', []));
        $this->assertSame('apple', Str::of('appel')->closest(['grape', 'apple', 'apply']));
    }
}
