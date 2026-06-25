<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use InvalidArgumentException;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Support\Username;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class UsernameTest extends TestCase
{
    public function test_for_generates_a_sanitised_lowercase_handle(): void
    {
        $this->assertSame('johndoe', Username::for('John Doe')->generate());
    }

    public function test_from_email_uses_the_local_part_with_dot_separator(): void
    {
        $this->assertSame('jane.doe', Username::fromEmail('Jane.Doe@example.com')->generate());
    }

    public function test_from_name_joins_with_configured_separator(): void
    {
        $this->assertSame('jane.doe', Username::fromName('Jane', 'Doe')->separator('.')->generate());
        $this->assertSame('jane_doe', Username::fromName('Jane', 'Doe')->separator('_')->generate());
        $this->assertSame('janedoe', Username::fromName('Jane', 'Doe')->generate());
    }

    public function test_random_is_a_handle_with_a_numeric_suffix(): void
    {
        $this->assertMatchesRegularExpression('/^user[0-9]{4}$/', Username::random()->minLength(1)->generate());

        $guest = Username::random('guest', 2)->minLength(1)->generate();
        $this->assertMatchesRegularExpression('/^guest[0-9]{2}$/', $guest);
    }

    public function test_random_digits_are_clamped(): void
    {
        $one = Username::random('user', 0)->minLength(1)->generate();
        $this->assertMatchesRegularExpression('/^user[0-9]$/', $one);

        $ten = Username::random('user', 99)->minLength(1)->generate();
        $this->assertMatchesRegularExpression('/^user[0-9]{10}$/', $ten);
    }

    public function test_ascii_transliterates_accented_names(): void
    {
        $this->assertSame('joaomuller', Username::fromName('João', 'Müller')->generate());
    }

    public function test_ascii_can_be_disabled(): void
    {
        // With transliteration off, non-ascii letters are simply stripped.
        $this->assertSame('joo', Username::for('João')->ascii(false)->generate());
    }

    public function test_uppercase_and_preserve_case(): void
    {
        $this->assertSame('JOHNDOE', Username::for('John Doe')->uppercase()->generate());
        $this->assertSame('JohnDoe', Username::for('John Doe')->preserveCase()->generate());
    }

    public function test_prefix_and_suffix_are_applied(): void
    {
        $this->assertSame('dev_jane', Username::for('jane')->prefix('dev_')->generate());
        $this->assertSame('jane_dev', Username::for('jane')->suffix('_dev')->generate());
    }

    public function test_random_suffix_appends_the_requested_digits(): void
    {
        $handle = Username::for('jane')->withRandomSuffix(3)->generate();

        $this->assertMatchesRegularExpression('/^jane[0-9]{3}$/', $handle);
    }

    public function test_separators_are_collapsed_and_trimmed(): void
    {
        $this->assertSame('jane.doe', Username::for('..jane..doe..')->generate());
        $this->assertSame('jane.doe', Username::for('jane...doe')->generate());
    }

    public function test_leading_non_alpha_is_prefixed_with_user(): void
    {
        $this->assertSame('user123', Username::for('123')->generate());
    }

    public function test_max_length_clamps_the_result(): void
    {
        $handle = Username::for('abcdefghijklmnop')->maxLength(8)->generate();

        $this->assertSame('abcdefgh', $handle);
    }

    public function test_min_length_pads_short_handles(): void
    {
        $handle = Username::for('ab')->minLength(5)->generate();

        $this->assertSame(5, mb_strlen($handle));
        $this->assertMatchesRegularExpression('/^ab[0-9]{3}$/', $handle);
    }

    public function test_allow_restricts_permitted_separators(): void
    {
        // Only dots survive; the underscore is stripped.
        $this->assertSame('janedoe', Username::for('jane_doe')->allow('.')->generate());
        // Stripping all separators.
        $this->assertSame('janedoe', Username::for('jane.doe')->allow('')->generate());
    }

    public function test_allow_rejects_unsafe_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Username::for('x')->allow('!');
    }

    public function test_invalid_separator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Username::for('x')->separator('/');
    }

    public function test_max_length_below_min_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Username::for('x')->minLength(5)->maxLength(3);
    }

    public function test_reserved_names_are_avoided_via_random_suffix(): void
    {
        $handle = Username::for('admin')->reserved(['admin'])->generate();

        $this->assertNotSame('admin', $handle);
        $this->assertStringStartsWith('admin', $handle);
    }

    public function test_unique_checker_walks_to_an_available_handle(): void
    {
        $taken = ['janedoe', 'janedoe1234'];

        $handle = Username::for('jane doe')
            ->unique(fn (string $u): bool => !in_array($u, $taken, true))
            ->generate();

        $this->assertNotContains($handle, $taken);
    }

    public function test_unique_loop_exhaustion_throws(): void
    {
        $this->expectException(RuntimeException::class);

        // Nothing is ever available -> the bounded loop gives up.
        Username::for('jane')->unique(static fn (): bool => false)->generate();
    }

    public function test_candidates_produce_canonical_name_variants(): void
    {
        $candidates = Username::fromName('Jane', 'Doe')->candidates(10);

        $this->assertContains('janedoe', $candidates);
        $this->assertContains('jane.doe', $candidates);
        $this->assertContains('jane_doe', $candidates);
        $this->assertContains('jdoe', $candidates);
        $this->assertContains('jane', $candidates);
        $this->assertContains('doe', $candidates);
        $this->assertSame($candidates, array_values(array_unique($candidates)));
        $this->assertNotContains('', $candidates);
        $this->assertLessThanOrEqual(10, count($candidates));
    }

    public function test_candidates_handle_a_single_name(): void
    {
        $candidates = Username::fromName('Madonna')->candidates(5);

        $this->assertContains('madonna', $candidates);
        $this->assertNotEmpty($candidates);
    }

    public function test_candidates_count_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Username::fromName('Jane', 'Doe')->candidates(0);
    }

    public function test_to_array_exposes_the_resolved_config(): void
    {
        $array = Username::fromName('Jane', 'Doe')->separator('.')->uppercase()->toArray();

        $this->assertSame('JANE.DOE', $array['username']);
        $this->assertSame('.', $array['separator']);
        $this->assertSame('upper', $array['case']);
        $this->assertTrue($array['ascii']);
    }

    public function test_to_string_never_throws_and_yields_empty_on_failure(): void
    {
        // An unsatisfiable uniqueness checker would throw from generate(); the
        // stringable contract swallows it.
        $username = Username::for('jane')->unique(static fn (): bool => false);

        $this->assertSame('', (string) $username);
    }

    public function test_builder_is_immutable(): void
    {
        $base = Username::for('Jane Doe');
        $upper = $base->uppercase();

        $this->assertSame('janedoe', $base->generate());
        $this->assertSame('JANEDOE', $upper->generate());
        $this->assertNotSame($base, $upper);
    }
}
