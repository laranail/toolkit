<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use Simtabi\Laranail\Toolkit\Helpers\XHelper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class XHelperTest extends TestCase
{
    public function test_array_trim_trims_only_string_values(): void
    {
        $this->assertSame(
            ['a', 'b', 3],
            XHelper::arrayTrim(['  a ', "b\n", 3]),
        );
    }

    public function test_array_flatten_collapses_nested_arrays(): void
    {
        $this->assertSame(
            [1, 2, 3, 4],
            XHelper::arrayFlatten([1, [2, [3, 4]]]),
        );
    }

    public function test_str_between_extracts_the_inner_substring(): void
    {
        $this->assertSame('value', XHelper::strBetween('[value]', '[', ']'));
        $this->assertNull(XHelper::strBetween('no markers', '[', ']'));
    }

    public function test_str_slugify_transliterates_unicode(): void
    {
        $this->assertSame('cafe-au-lait', XHelper::strSlugify('Café au lait'));
        $this->assertSame('hello-world', XHelper::strSlugify('Hello, World!'));
    }

    public function test_uuid_is_a_valid_v4_uuid(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            XHelper::uuid(),
        );
    }

    // --- Folded-in StringHelperService delta ---

    public function test_uc_words_title_cases_multibyte(): void
    {
        $this->assertSame('Hello World', XHelper::ucWords('hello world'));
        $this->assertSame('Élan Vital', XHelper::ucWords('élan vital'));
    }

    public function test_username_from_email_strips_and_prefixes(): void
    {
        $this->assertSame('john.doe', XHelper::usernameFromEmail('john.doe@example.com'));
        // Local part starting with a non-letter gets a user_ prefix.
        $this->assertSame('user_123abc', XHelper::usernameFromEmail('123abc@example.com'));
        // Nothing usable falls back to "user".
        $this->assertSame('user', XHelper::usernameFromEmail('!!!@example.com'));
    }

    public function test_email_from_username_appends_domain_or_passes_through(): void
    {
        $this->assertSame('jane@example.com', XHelper::emailFromUsername('jane'));
        $this->assertSame('jane@corp.test', XHelper::emailFromUsername('jane', 'corp.test'));
        // Already an email — returned unchanged.
        $this->assertSame('jane@x.com', XHelper::emailFromUsername('jane@x.com'));
    }

    // --- Folded-in Username (pheg-free native generator) ---

    public function test_name_to_usernames_generates_native_candidates(): void
    {
        $candidates = XHelper::nameToUsernames('Jane', 'Doe');

        $this->assertContains('janedoe', $candidates);
        $this->assertContains('jane.doe', $candidates);
        $this->assertContains('jdoe', $candidates);
        // No duplicates and no empty entries.
        $this->assertSame($candidates, array_values(array_unique($candidates)));
        $this->assertNotContains('', $candidates);
    }

    public function test_name_to_usernames_handles_missing_last_name(): void
    {
        $candidates = XHelper::nameToUsernames('Madonna');

        $this->assertContains('madonna', $candidates);
        $this->assertNotEmpty($candidates);
    }

    public function test_name_to_usernames_empty_for_blank_input(): void
    {
        $this->assertSame([], XHelper::nameToUsernames('', ''));
    }
}
