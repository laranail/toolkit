<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Security;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Modules\Security\Password;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class PasswordTest extends TestCase
{
    public function test_strong_preset_uses_all_classes(): void
    {
        $password = Password::strong()->generate();

        $this->assertSame(20, strlen($password));
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
        $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $password);
    }

    public function test_alphanumeric_preset_has_no_symbols(): void
    {
        $password = Password::alphanumeric()->generate();

        $this->assertSame(16, strlen($password));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $password);
    }

    public function test_numeric_preset_is_digits_only(): void
    {
        $password = Password::numeric()->generate();

        $this->assertSame(6, strlen($password));
        $this->assertMatchesRegularExpression('/^[0-9]+$/', $password);
    }

    public function test_basic_preset_is_lowercase_and_digits(): void
    {
        $password = Password::basic()->generate();

        $this->assertSame(12, strlen($password));
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $password);
    }

    public function test_length_is_respected(): void
    {
        $this->assertSame(40, strlen(Password::strong()->length(40)->generate()));
    }

    public function test_require_each_class_guarantees_coverage(): void
    {
        // Run repeatedly: every result must contain all four classes.
        for ($i = 0; $i < 50; $i++) {
            $password = Password::strong()->length(8)->requireEachClass()->generate();

            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
            $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $password);
        }
    }

    public function test_exclude_ambiguous_removes_confusable_glyphs(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $password = Password::strong()
                ->symbols(false)
                ->length(40)
                ->excludeAmbiguous()
                ->generate();

            $this->assertStringNotContainsString('0', $password);
            $this->assertStringNotContainsString('O', $password);
            $this->assertStringNotContainsString('1', $password);
            $this->assertStringNotContainsString('l', $password);
            $this->assertStringNotContainsString('I', $password);
        }
    }

    public function test_metadata_reports_correct_entropy(): void
    {
        $meta = Password::alphanumeric()->length(16)->requireEachClass(false)->generateWithMetadata();

        $this->assertSame(16, $meta['length']);
        $this->assertSame(62, $meta['charset_size']); // a-z A-Z 0-9
        $this->assertEqualsWithDelta(16 * log(62, 2), $meta['entropy'], 0.0001);
        $this->assertSame(strlen($meta['password']), 16);
    }

    public function test_no_class_selected_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);

        Password::basic()
            ->lowercase(false)
            ->uppercase(false)
            ->digits(false)
            ->symbols(false)
            ->generate();
    }

    public function test_impossible_entropy_throws_runtime_exception(): void
    {
        $this->expectException(RuntimeException::class);

        // 8 lowercase-only chars cap at 8*log2(26) ≈ 37.6 bits — 128 is unreachable.
        Password::basic()
            ->uppercase(false)
            ->digits(false)
            ->symbols(false)
            ->length(8)
            ->minEntropy(128)
            ->generate();
    }

    public function test_achievable_min_entropy_succeeds(): void
    {
        $password = Password::strong()->length(20)->minEntropy(80)->generate();

        $this->assertSame(20, strlen($password));
    }

    public function test_negative_min_entropy_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Password::strong()->minEntropy(-1.0);
    }

    public function test_zero_length_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Password::strong()->length(0);
    }

    public function test_to_string_yields_a_password(): void
    {
        $this->assertSame(20, strlen((string) Password::strong()));
    }

    public function test_builder_is_immutable(): void
    {
        $base = Password::strong()->length(16);
        $longer = $base->length(32);

        $this->assertSame(16, strlen($base->generate()));
        $this->assertSame(32, strlen($longer->generate()));
        $this->assertNotSame($base, $longer);
    }

    #[Group('security')]
    public function test_passwords_are_high_entropy_with_no_collisions(): void
    {
        $seen = [];
        for ($i = 0; $i < 2000; $i++) {
            $seen[Password::strong()->generate()] = true;
        }

        $this->assertCount(2000, $seen);
    }
}
