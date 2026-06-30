<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use InvalidArgumentException;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Support\Username;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Exhaustive, exact-output behaviour coverage for the Username builder.
 *
 * Every assertion pins a concrete contract (precise strings, lengths and
 * boundaries) so arithmetic, boundary, string-manipulation and return-value
 * mutants cannot survive. Platform-dependent transliteration (iconv) is only
 * asserted via stable contracts (ASCII-only output, stable prefix), never via
 * an exact transliteration that may differ Mac vs Linux.
 */
final class UsernameBehaviorTest extends TestCase
{
    // --- Entry points: source resolution -------------------------------------

    public function test_for_uses_the_raw_string_as_source(): void
    {
        $this->assertSame('johndoe', Username::for('John Doe')->generate());
        $this->assertSame('jane.doe', Username::for('jane.doe')->generate());
    }

    public function test_from_email_takes_local_part_and_defaults_to_dot_separator(): void
    {
        $this->assertSame('jane.doe', Username::fromEmail('Jane.Doe@example.com')->generate());
        $this->assertSame('.', Username::fromEmail('a@b.com')->toArray()['separator']);
    }

    public function test_from_email_leading_digit_uses_the_dot_separator_when_prefixing_user(): void
    {
        // The '.' separator carries into enforceLeadingAlpha's `user<sep><value>`.
        $this->assertSame('user.123', Username::fromEmail('123@example.com')->generate());
    }

    public function test_from_name_joins_filtered_parts_with_the_separator(): void
    {
        $this->assertSame('janedoe', Username::fromName('Jane', 'Doe')->generate());
        $this->assertSame('jane.doe', Username::fromName('Jane', 'Doe')->separator('.')->generate());
        $this->assertSame('jane_doe', Username::fromName('Jane', 'Doe')->separator('_')->generate());
        $this->assertSame('jane-doe', Username::fromName('Jane', 'Doe')->separator('-')->generate());
    }

    public function test_from_name_with_only_first_or_only_last_drops_the_empty_part(): void
    {
        $this->assertSame('jane', Username::fromName('Jane')->separator('.')->generate());
        $this->assertSame('jane', Username::fromName('Jane', '')->separator('.')->generate());
        $this->assertSame('doe', Username::fromName('', 'Doe')->separator('.')->generate());
    }

    public function test_from_name_with_two_empty_parts_falls_back_to_a_random_user_handle(): void
    {
        $this->assertMatchesRegularExpression('/^user[0-9]{4}$/', Username::fromName('', '')->generate());
    }

    public function test_from_name_leading_digit_uses_the_separator_when_prefixing_user(): void
    {
        $this->assertSame('user.123.456', Username::fromName('123', '456')->separator('.')->generate());
    }

    public function test_random_builds_prefix_plus_fixed_width_digits(): void
    {
        $this->assertMatchesRegularExpression('/^user[0-9]{4}$/', Username::random()->generate());

        $guest = Username::random('guest', 2)->minLength(1)->generate();
        $this->assertMatchesRegularExpression('/^guest[0-9]{2}$/', $guest);
        $this->assertSame(7, mb_strlen($guest));
    }

    public function test_random_prefix_is_lowercased_and_stripped_to_letters(): void
    {
        $this->assertMatchesRegularExpression('/^dev[0-9]{2}$/', Username::random('Dev123', 2)->minLength(1)->generate());
    }

    public function test_random_prefix_with_no_letters_falls_back_to_user(): void
    {
        $this->assertMatchesRegularExpression('/^user[0-9]{2}$/', Username::random('123', 2)->minLength(1)->generate());
        $this->assertMatchesRegularExpression('/^user[0-9]{2}$/', Username::random('---', 2)->minLength(1)->generate());
    }

    // --- Casing --------------------------------------------------------------

    public function test_default_case_is_lowercase(): void
    {
        $this->assertSame('johndoe', Username::for('JOHN DOE')->generate());
        $this->assertSame('lower', Username::for('x')->toArray()['case']);
    }

    public function test_uppercase_forces_upper(): void
    {
        $this->assertSame('JOHNDOE', Username::for('John Doe')->uppercase()->generate());
        $this->assertSame('upper', Username::for('x')->uppercase()->toArray()['case']);
    }

    public function test_preserve_case_keeps_the_source_casing(): void
    {
        $this->assertSame('JohnDoe', Username::for('John Doe')->preserveCase()->generate());
        $this->assertSame('preserve', Username::for('x')->preserveCase()->toArray()['case']);
    }

    // --- ASCII / transliteration --------------------------------------------

    public function test_ascii_is_on_by_default_and_yields_ascii_only_with_a_stable_prefix(): void
    {
        $handle = Username::fromName('João', 'Müller')->generate();

        $this->assertMatchesRegularExpression('/^[a-z0-9._-]+$/', $handle);
        $this->assertStringStartsWith('joao', $handle);
        $this->assertTrue(Username::for('x')->toArray()['ascii']);
    }

    public function test_ascii_can_be_disabled_so_non_ascii_letters_are_dropped(): void
    {
        // No transliteration: accented letters are stripped, ascii letters survive.
        $this->assertSame('rene', Username::for('Renée')->ascii(false)->generate());
        $this->assertFalse(Username::for('x')->ascii(false)->toArray()['ascii']);
    }

    // --- Separator validation & meaning -------------------------------------

    public function test_all_allowed_separators_are_accepted(): void
    {
        foreach (['', '.', '_', '-'] as $sep) {
            $this->assertSame($sep, Username::for('x')->separator($sep)->toArray()['separator']);
        }
    }

    public function test_invalid_separator_throws_with_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid separator');

        Username::for('x')->separator('/');
    }

    public function test_multi_character_separator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Username::for('x')->separator('..');
    }

    // --- Prefix / suffix -----------------------------------------------------

    public function test_prefix_and_suffix_are_applied_and_keep_their_separators(): void
    {
        $this->assertSame('dev_jane', Username::for('jane')->prefix('dev_')->generate());
        $this->assertSame('jane_dev', Username::for('jane')->suffix('_dev')->generate());
        $this->assertSame('x-jane', Username::for('jane')->prefix('x-')->generate());
    }

    public function test_prefix_and_suffix_are_sanitised_of_unsafe_characters(): void
    {
        $this->assertSame('devjane', Username::for('jane')->prefix('dev!')->generate());
    }

    public function test_empty_prefix_and_suffix_are_no_ops(): void
    {
        $this->assertSame('jane', Username::for('jane')->prefix('')->suffix('')->generate());
    }

    public function test_to_array_reports_the_raw_prefix_and_suffix(): void
    {
        $array = Username::for('jane')->prefix('dev')->suffix('io')->toArray();

        $this->assertSame('dev', $array['prefix']);
        $this->assertSame('io', $array['suffix']);
    }

    // --- Random suffix (with internal digit clamping) ------------------------

    public function test_random_suffix_appends_exactly_the_requested_digits(): void
    {
        $handle = Username::for('jane')->withRandomSuffix(3)->generate();

        $this->assertMatchesRegularExpression('/^jane[0-9]{3}$/', $handle);
        $this->assertSame(7, mb_strlen($handle));
    }

    public function test_random_suffix_digit_count_is_clamped_to_one_at_the_low_end(): void
    {
        $this->assertMatchesRegularExpression('/^jane[0-9]$/', Username::for('jane')->withRandomSuffix(0)->generate());
        $this->assertMatchesRegularExpression('/^jane[0-9]$/', Username::for('jane')->withRandomSuffix(-5)->generate());
    }

    public function test_random_suffix_digit_count_is_clamped_to_ten_at_the_high_end(): void
    {
        $this->assertMatchesRegularExpression('/^jane[0-9]{10}$/', Username::for('jane')->withRandomSuffix(99)->generate());
    }

    // --- Separator stripping / restriction ----------------------------------

    public function test_allow_restricts_which_separators_survive(): void
    {
        $this->assertSame('janedoe.smithjr', Username::for('jane_doe.smith-jr')->allow('.')->generate());
        $this->assertSame('janedoesmithjr', Username::for('jane_doe.smith-jr')->allow('')->generate());
    }

    public function test_allow_can_permit_all_three_safe_separators(): void
    {
        $this->assertSame('j.a_n-e', Username::for('j.a_n-e')->allow('._-')->generate());
    }

    public function test_allow_rejects_a_space_with_a_specific_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('space is not an allowable');

        Username::for('x')->allow(' ');
    }

    public function test_allow_rejects_an_unsafe_character_with_a_specific_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not allowed');

        Username::for('x')->allow('!');
    }

    public function test_allow_rejects_a_letter_outside_the_safe_set(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Username::for('x')->allow('a');
    }

    public function test_alphanumeric_only_drops_every_separator(): void
    {
        $this->assertSame('janedoe', Username::for('jane.doe')->alphanumericOnly()->generate());
        $this->assertSame('jane', Username::for('j.a_n-e')->alphanumericOnly()->generate());
        $this->assertSame('janedoe', Username::fromName('Jane', 'Doe')->separator('.')->alphanumericOnly()->generate());
    }

    // --- Separator collapse / trim ------------------------------------------

    public function test_runs_of_a_single_separator_collapse_to_one(): void
    {
        $this->assertSame('jane.doe', Username::for('jane...doe')->generate());
        $this->assertSame('j.a_b', Username::for('j..a__b')->generate());
    }

    public function test_runs_of_mixed_separators_collapse_to_the_first(): void
    {
        $this->assertSame('j.a', Username::for('j.-_a')->generate());
    }

    public function test_leading_and_trailing_separators_are_trimmed(): void
    {
        $this->assertSame('jane.doe', Username::for('..jane.doe..')->generate());
        $this->assertSame('jane', Username::for('--jane--')->generate());
    }

    // --- Leading-alpha enforcement ------------------------------------------

    public function test_a_leading_non_letter_is_prefixed_with_user(): void
    {
        $this->assertSame('user123', Username::for('123')->generate());
    }

    public function test_a_leading_letter_is_left_untouched(): void
    {
        $this->assertSame('abc', Username::for('abc')->generate());
    }

    public function test_an_all_separator_source_recovers_via_the_uniqueness_retry(): void
    {
        // finalise('...') collapses/trims to '', which is unacceptable; the retry
        // appends digits and the leading-digit handle becomes user####.
        $this->assertMatchesRegularExpression('/^user[0-9]{4}$/', Username::for('...')->generate());
    }

    // --- Length: minimum padding --------------------------------------------

    public function test_short_handles_are_padded_up_to_min_length(): void
    {
        $handle = Username::for('ab')->minLength(5)->generate();

        $this->assertSame(5, mb_strlen($handle));
        $this->assertMatchesRegularExpression('/^ab[0-9]{3}$/', $handle);
    }

    public function test_a_handle_exactly_at_min_length_is_not_padded(): void
    {
        // Off-by-one guard: `< minLength` must not pad a handle already at the floor.
        $this->assertSame('abc', Username::for('abc')->minLength(3)->generate());
    }

    public function test_default_min_length_is_three(): void
    {
        $this->assertSame(3, Username::for('x')->toArray()['minLength']);
        $this->assertMatchesRegularExpression('/^ab[0-9]$/', Username::for('ab')->generate());
    }

    // --- Length: maximum truncation -----------------------------------------

    public function test_long_handles_are_truncated_to_max_length(): void
    {
        $this->assertSame('abcdefgh', Username::for('abcdefghijklmnop')->maxLength(8)->generate());
    }

    public function test_truncation_strips_a_trailing_separator_left_at_the_cut(): void
    {
        // substr lands on 'abcdefg.' then rtrim removes the dangling separator.
        $this->assertSame('abcdefg', Username::for('abcdefg.hij')->maxLength(8)->generate());
    }

    public function test_a_handle_exactly_at_max_length_is_not_truncated(): void
    {
        $this->assertSame('abcde', Username::for('abcde')->minLength(5)->maxLength(5)->generate());
    }

    public function test_default_max_length_is_thirty(): void
    {
        $this->assertSame(30, Username::for('x')->toArray()['maxLength']);
    }

    // --- Length validation boundaries ---------------------------------------

    public function test_max_length_below_one_throws_its_own_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxLength must be >= 1');

        Username::for('x')->minLength(1)->maxLength(0);
    }

    public function test_max_length_below_min_length_throws_its_own_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be >= minLength');

        Username::for('x')->minLength(5)->maxLength(3);
    }

    public function test_max_length_equal_to_min_length_is_allowed(): void
    {
        // Boundary: `< minLength` (not `<=`) must accept max == min.
        $this->assertSame(5, Username::for('x')->minLength(5)->maxLength(5)->toArray()['maxLength']);
    }

    public function test_max_length_of_one_is_allowed_when_min_length_is_one(): void
    {
        $this->assertSame(1, Username::for('x')->minLength(1)->maxLength(1)->toArray()['maxLength']);
    }

    public function test_min_length_below_one_throws_its_own_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minLength must be >= 1');

        Username::for('x')->minLength(0);
    }

    public function test_min_length_above_max_length_throws_its_own_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be <= maxLength');

        Username::for('x')->minLength(31);
    }

    public function test_min_length_equal_to_max_length_is_allowed(): void
    {
        // Boundary: `> maxLength` (not `>=`) must accept min == max.
        $this->assertSame(30, Username::for('x')->minLength(30)->toArray()['minLength']);
    }

    public function test_min_length_of_one_is_allowed(): void
    {
        $this->assertSame(1, Username::for('x')->minLength(1)->toArray()['minLength']);
    }

    // --- Reserved words ------------------------------------------------------

    public function test_reserved_names_are_normalised_lowercased_trimmed_and_deduped(): void
    {
        $reserved = Username::for('x')
            ->reserved(['', '  ', 'Root', 'ROOT', ' admin '])
            ->toArray()['reserved'];

        $this->assertSame(['root', 'admin'], $reserved);
    }

    public function test_a_reserved_primary_handle_is_skipped_via_random_suffix(): void
    {
        $handle = Username::for('admin')->reserved(['admin'])->generate();

        $this->assertMatchesRegularExpression('/^admin[0-9]{4}$/', $handle);
    }

    public function test_reserved_matching_is_case_insensitive_against_the_candidate(): void
    {
        // preserveCase keeps 'Admin', but the reserved check lowercases it first.
        $handle = Username::for('Admin')->preserveCase()->reserved(['admin'])->generate();

        $this->assertMatchesRegularExpression('/^Admin[0-9]{4}$/', $handle);
    }

    // --- Uniqueness loop -----------------------------------------------------

    public function test_uniqueness_retry_appends_four_digits_after_the_separator(): void
    {
        $handle = Username::fromName('jane', 'doe')
            ->separator('.')
            ->unique(static fn (string $u): bool => $u !== 'jane.doe')
            ->generate();

        $this->assertMatchesRegularExpression('/^jane\.doe\.[0-9]{4}$/', $handle);
    }

    public function test_uniqueness_retry_with_empty_separator_appends_bare_digits(): void
    {
        $handle = Username::for('jane')
            ->unique(static fn (string $u): bool => $u !== 'jane')
            ->generate();

        $this->assertMatchesRegularExpression('/^jane[0-9]{4}$/', $handle);
    }

    public function test_unique_checker_must_return_strict_true_to_accept(): void
    {
        $available = static fn (string $u): bool => $u === 'janedoe';

        $this->assertSame('janedoe', Username::for('janedoe')->unique($available)->generate());
    }

    public function test_an_unsatisfiable_checker_exhausts_the_bounded_loop(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('100 attempts');

        Username::for('jane')->unique(static fn (): bool => false)->generate();
    }

    // --- candidates() --------------------------------------------------------

    public function test_candidates_emits_the_canonical_name_variants_in_order(): void
    {
        $this->assertSame(
            ['janedoe', 'jane.doe', 'jane_doe', 'jdoe', 'jane.d', 'jane', 'doe'],
            Username::fromName('Jane', 'Doe')->candidates(7),
        );
    }

    public function test_candidates_respects_the_requested_count(): void
    {
        $this->assertSame(
            ['janedoe', 'jane.doe', 'jane_doe'],
            Username::fromName('Jane', 'Doe')->candidates(3),
        );

        $this->assertSame(
            ['janedoe', 'jane.doe', 'jane_doe', 'jdoe', 'jane.d'],
            Username::fromName('Jane', 'Doe')->candidates(5),
        );
    }

    public function test_candidates_apply_the_configured_casing(): void
    {
        $this->assertSame(
            ['JANEDOE', 'JANE.DOE', 'JANE_DOE', 'JDOE', 'JANE.D', 'JANE', 'DOE'],
            Username::fromName('Jane', 'Doe')->uppercase()->candidates(7),
        );
    }

    public function test_candidates_pad_beyond_the_canonical_set_with_numeric_variants(): void
    {
        $candidates = Username::fromName('Jane', 'Doe')->candidates(10);

        $this->assertCount(10, $candidates);
        $this->assertSame(
            ['janedoe', 'jane.doe', 'jane_doe', 'jdoe', 'jane.d', 'jane', 'doe'],
            array_slice($candidates, 0, 7),
        );

        foreach (array_slice($candidates, 7) as $padded) {
            $this->assertMatchesRegularExpression('/^janedoe[0-9]{3}$/', $padded);
        }

        $this->assertSame($candidates, array_values(array_unique($candidates)));
    }

    public function test_candidates_for_a_single_name_returns_just_that_handle(): void
    {
        $this->assertSame(['madonna'], Username::fromName('Madonna')->candidates(1));
    }

    public function test_candidates_for_two_empty_parts_returns_an_empty_list(): void
    {
        $this->assertSame([], Username::fromName('', '')->candidates(5));
    }

    public function test_candidates_count_must_be_at_least_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('candidates count must be >= 1');

        Username::fromName('Jane', 'Doe')->candidates(0);
    }

    // --- toArray() -----------------------------------------------------------

    public function test_to_array_exposes_the_full_resolved_configuration(): void
    {
        $array = Username::fromName('Jane', 'Doe')
            ->separator('.')
            ->prefix('dev')
            ->suffix('io')
            ->minLength(2)
            ->maxLength(25)
            ->reserved(['root'])
            ->toArray();

        $this->assertSame([
            'username' => 'devjane.doeio',
            'separator' => '.',
            'case' => 'lower',
            'ascii' => true,
            'minLength' => 2,
            'maxLength' => 25,
            'prefix' => 'dev',
            'suffix' => 'io',
            'reserved' => ['root'],
        ], $array);
    }

    // --- __toString() --------------------------------------------------------

    public function test_to_string_returns_the_generated_handle(): void
    {
        $this->assertSame('janedoe', (string) Username::fromName('Jane', 'Doe'));
    }

    public function test_to_string_swallows_generation_failure_and_returns_empty(): void
    {
        $this->assertSame('', (string) Username::for('jane')->unique(static fn (): bool => false));
        $this->assertSame('', (string) Username::for('john doe')->rejectSpaces());
    }

    // --- Whitespace handling -------------------------------------------------

    public function test_spaces_in_the_source_are_silently_stripped_by_default(): void
    {
        $this->assertSame('johndoe', Username::for('john doe')->generate());
        $this->assertSame('abc', Username::for('  a b  c  ')->generate());
    }

    public function test_reject_spaces_fails_loudly_on_a_spaced_for_source(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not contain whitespace');

        Username::for('john doe')->rejectSpaces()->generate();
    }

    public function test_reject_spaces_fails_on_any_whitespace_not_just_a_space(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Username::for("john\tdoe")->rejectSpaces()->generate();
    }

    public function test_reject_spaces_inspects_each_name_part(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Username::fromName('john doe', 'smith')->rejectSpaces()->generate();
    }

    public function test_reject_spaces_passes_a_clean_source(): void
    {
        $this->assertSame('johndoe', Username::for('johndoe')->rejectSpaces()->generate());
    }

    public function test_reject_spaces_does_not_break_random_mode(): void
    {
        $this->assertMatchesRegularExpression(
            '/^user[0-9]{4}$/',
            Username::random()->rejectSpaces()->generate(),
        );
    }

    public function test_a_generated_handle_never_contains_a_space(): void
    {
        $this->assertStringNotContainsString(' ', Username::for('  a b  c  ')->generate());
        $this->assertStringNotContainsString(' ', Username::fromName('John', 'Doe')->generate());
    }

    // --- Immutability --------------------------------------------------------

    public function test_chain_methods_return_a_fresh_instance(): void
    {
        $base = Username::for('jane');
        $prefixed = $base->prefix('dev');

        $this->assertNotSame($base, $prefixed);
        $this->assertSame('jane', $base->generate());
        $this->assertSame('devjane', $prefixed->generate());
    }
}
