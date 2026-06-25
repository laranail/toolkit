<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Security;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Simtabi\Laranail\Toolkit\Modules\Security\Passphrase;
use Simtabi\Laranail\Toolkit\Modules\Security\SecurityData;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class PassphraseTest extends TestCase
{
    public function test_memorable_default_is_six_hyphenated_words(): void
    {
        $passphrase = Passphrase::memorable()->generate();

        $this->assertCount(6, explode('-', $passphrase));
    }

    public function test_default_aliases_memorable(): void
    {
        $this->assertCount(6, explode('-', Passphrase::default()->generate()));
    }

    public function test_word_count_is_respected(): void
    {
        $passphrase = Passphrase::memorable()->wordCount(4)->generate();

        $this->assertCount(4, explode('-', $passphrase));
    }

    public function test_separator_variants(): void
    {
        $this->assertStringContainsString('_', Passphrase::memorable()->wordCount(3)->separator('_')->generate());
        $this->assertStringContainsString(' ', Passphrase::memorable()->wordCount(3)->separator(' ')->generate());

        $joined = Passphrase::memorable()->wordCount(3)->separator('')->generate();
        $this->assertStringNotContainsString('-', $joined);
        $this->assertStringNotContainsString(' ', $joined);
    }

    public function test_capitalize_none_is_all_lowercase(): void
    {
        $passphrase = Passphrase::memorable()->capitalize('none')->generate();

        $this->assertSame(strtolower($passphrase), $passphrase);
    }

    public function test_capitalize_all_is_uppercase(): void
    {
        $passphrase = Passphrase::memorable()->capitalize('all')->generate();

        $this->assertSame(strtoupper($passphrase), $passphrase);
    }

    public function test_capitalize_first_capitalises_only_the_first_word(): void
    {
        $words = explode('-', Passphrase::memorable()->wordCount(3)->capitalize('first')->generate());

        $this->assertSame(ucfirst($words[0]), $words[0]);
        $this->assertSame(strtolower($words[1]), $words[1]);
        $this->assertSame(strtolower($words[2]), $words[2]);
    }

    public function test_capitalize_title_capitalises_every_word(): void
    {
        $words = explode('-', Passphrase::memorable()->wordCount(3)->capitalize('title')->generate());

        foreach ($words as $word) {
            $this->assertSame(ucfirst($word), $word);
        }
    }

    public function test_with_number_appends_digits(): void
    {
        $passphrase = Passphrase::memorable()->wordCount(3)->withNumber(4)->generate();
        $parts = explode('-', $passphrase);

        $this->assertMatchesRegularExpression('/^[0-9]{4}$/', end($parts));
    }

    public function test_with_symbol_appends_a_symbol(): void
    {
        $passphrase = Passphrase::memorable()->wordCount(3)->withSymbol('!')->generate();

        $this->assertStringEndsWith('!', $passphrase);
    }

    public function test_with_random_symbol_appends_one_symbol(): void
    {
        // Use a space separator so the trailing symbol token is unambiguous even
        // when the random symbol happens to be a hyphen/underscore.
        $passphrase = Passphrase::memorable()->wordCount(3)->separator(' ')->withSymbol()->generate();
        $parts = explode(' ', $passphrase);

        $this->assertCount(4, $parts); // 3 words + 1 symbol token
        $this->assertSame(1, strlen((string) end($parts)));
        $this->assertMatchesRegularExpression('/^[!@#$%^&*?\-_+=]$/', (string) end($parts));
    }

    public function test_metadata_reports_entropy_and_words(): void
    {
        $meta = Passphrase::memorable()->wordCount(6)->generateWithMetadata();

        $this->assertSame(6, $meta['word_count']);
        $this->assertCount(6, $meta['words']);
        // 6 * log2(7776) ≈ 77.55 bits
        $this->assertEqualsWithDelta(6 * log(7776, 2), $meta['entropy'], 0.0001);
        $this->assertEqualsWithDelta(77.5, $meta['entropy'], 0.1);
    }

    public function test_metadata_includes_number_and_symbol_tokens(): void
    {
        $meta = Passphrase::memorable()
            ->wordCount(3)
            ->withNumber(2)
            ->withSymbol('!')
            ->generateWithMetadata();

        $this->assertCount(3, $meta['words']);
        $this->assertMatchesRegularExpression('/-[0-9]{2}-!$/', $meta['passphrase']);
    }

    public function test_word_count_out_of_range_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Passphrase::memorable()->wordCount(0);
    }

    public function test_word_count_too_large_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Passphrase::memorable()->wordCount(21);
    }

    public function test_invalid_separator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Passphrase::memorable()->separator('/');
    }

    public function test_invalid_capitalize_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Passphrase::memorable()->capitalize('weird');
    }

    public function test_with_number_zero_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Passphrase::memorable()->withNumber(0);
    }

    public function test_to_string_yields_a_passphrase(): void
    {
        $this->assertCount(6, explode('-', (string) Passphrase::memorable()));
    }

    public function test_builder_is_immutable(): void
    {
        $base = Passphrase::memorable()->wordCount(4);
        $more = $base->wordCount(8);

        $this->assertCount(4, explode('-', $base->generate()));
        $this->assertCount(8, explode('-', $more->generate()));
        $this->assertNotSame($base, $more);
    }

    #[Group('security')]
    public function test_words_come_from_the_eff_wordlist(): void
    {
        $list = SecurityData::passphraseWords();
        $this->assertCount(7776, $list);

        $words = Passphrase::memorable()->wordCount(10)->generateWithMetadata()['words'];

        foreach ($words as $word) {
            $this->assertContains($word, $list);
        }
    }

    #[Group('security')]
    public function test_wordlist_is_static_cached_and_loaded_once(): void
    {
        // Reset the static cache, then generate twice; the cache must be populated
        // after the first generate() and reused (not nulled) on the second.
        $reflection = new ReflectionClass(Passphrase::class);
        $property = $reflection->getProperty('wordlist');
        $property->setValue(null, null);

        $this->assertNull($property->getValue());

        Passphrase::memorable()->generate();
        $afterFirst = $property->getValue();

        $this->assertIsArray($afterFirst);
        $this->assertCount(7776, $afterFirst);

        Passphrase::memorable()->generate();

        // Same cached array instance — not reloaded.
        $this->assertSame($afterFirst, $property->getValue());
    }

    #[Group('security')]
    public function test_passphrases_are_high_entropy_with_no_collisions(): void
    {
        $seen = [];
        for ($i = 0; $i < 1000; $i++) {
            $seen[Passphrase::memorable()->generate()] = true;
        }

        $this->assertCount(1000, $seen);
    }
}
