<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Rules\RejectCommonPasswords;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class RejectCommonPasswordsTest extends TestCase
{
    private RejectCommonPasswords $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new RejectCommonPasswords();
    }

    private function fails(RejectCommonPasswords $rule, mixed $value): bool
    {
        return ValidatorFacade::make(['password' => $value], ['password' => [$rule]])->fails();
    }

    public function test_rejects_common_passwords()
    {
        $commonPasswords = [
            'password',
            '123456',
            '123456789',
            'qwerty',
            'abc123',
            'password123',
            'admin',
            'letmein',
            'welcome',
            'monkey',
        ];

        foreach ($commonPasswords as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
            $this->assertStringContainsString(
                'common password that is not allowed',
                $validator->errors()->first('password')
            );
        }
    }

    public function test_accepts_strong_passwords()
    {
        $strongPasswords = [
            'MyStr0ng!P@ssw0rd',
            'ComplexP@ssw0rd123!',
            'VerySecureP@ssw0rd2024',
            'RandomString123!@#',
            'AnotherSecureP@ssw0rd',
        ];

        foreach ($strongPasswords as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertFalse($validator->fails());
        }
    }

    public function test_case_insensitive_rejection()
    {
        $commonPasswords = [
            'PASSWORD',
            'Password',
            'PaSsWoRd',
            'ADMIN',
            'Admin',
            'AdMiN',
        ];

        foreach ($commonPasswords as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_handles_whitespace()
    {
        $passwordsWithWhitespace = [
            ' password',
            'password ',
            ' password ',
            "\tpassword",
            "password\t",
        ];

        foreach ($passwordsWithWhitespace as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_accepts_non_string_values()
    {
        $nonStringValues = [
            null,
            123,
            [],
            true,
            false,
        ];

        foreach ($nonStringValues as $value) {
            $validator = ValidatorFacade::make(
                ['password' => $value],
                ['password' => [$this->rule]]
            );

            $this->assertFalse($validator->fails());
        }
    }

    public function test_rejects_numeric_sequences()
    {
        $numericSequences = [
            '111111',
            '000000',
            '666666',
            '888888',
            '999999',
            '11111111',
            '00000000',
            '123321',
            '654321',
        ];

        foreach ($numericSequences as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_rejects_keyboard_patterns()
    {
        $keyboardPatterns = [
            'qwerty',
            'qwertyuiop',
            'asdfghjkl',
            'zxcvbnm',
            'qazwsx',
        ];

        foreach ($keyboardPatterns as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_rejects_common_words_with_numbers()
    {
        $commonWithNumbers = [
            'password1',
            'password12',
            'password123',
            'admin1',
            'admin12',
            'admin123',
            'user1',
            'user12',
            'user123',
        ];

        foreach ($commonWithNumbers as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_accepts_mixed_case_strong_passwords()
    {
        $mixedCasePasswords = [
            'MyPassword123!',
            'SecurePass2024',
            'ComplexP@ssw0rd',
            'StrongP@ssw0rd123',
        ];

        foreach ($mixedCasePasswords as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertFalse($validator->fails());
        }
    }

    public function test_rule_implements_validation_rule_interface()
    {
        $this->assertInstanceOf(
            ValidationRule::class,
            $this->rule
        );
    }

    public function test_min_length_gate_rejects_short_passwords(): void
    {
        $rule = new RejectCommonPasswords(minLength: 12);

        $this->assertTrue($this->fails($rule, 'Short9!'));
        $this->assertFalse($this->fails($rule, 'LongEnoughPassphrase42'));
    }

    public function test_min_entropy_gate_rejects_low_entropy_passwords(): void
    {
        // 'aaaaaaaa' uses a single class (pool 26) -> 8 * log2(26) ~= 37.6 bits.
        $rule = new RejectCommonPasswords(minEntropy: 60);

        $this->assertTrue($this->fails($rule, 'aaaaaaaa'));
        // Mixed classes over a decent length clear the bar.
        $this->assertFalse($this->fails($rule, 'Tr0ub4dor&3xtraLong'));
    }

    public function test_fluent_config_builds_an_equivalent_rule(): void
    {
        $rule = RejectCommonPasswords::config()
            ->minLength(12)
            ->minEntropy(40)
            ->rule();

        $this->assertInstanceOf(RejectCommonPasswords::class, $rule);
        $this->assertTrue($this->fails($rule, 'tiny'));
    }

    public function test_hibp_fails_open_on_non_200_response(): void
    {
        // A 500 from the range API must NOT block the password (fail open).
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('upstream error', 500),
        ]);

        $rule = (new RejectCommonPasswords(checkHibp: true));

        $this->assertFalse($this->fails($rule, 'SomeUniquePassphrase-9182'));
    }

    public function test_hibp_fails_open_on_transport_error(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('connection timed out');
        });

        $rule = (new RejectCommonPasswords(checkHibp: true));

        $this->assertFalse($this->fails($rule, 'AnotherUniquePassphrase-7361'));
    }

    public function test_hibp_rejects_a_breached_password_when_suffix_matches(): void
    {
        $password = 'P@ssw0rd-breached-fixture';
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        Http::fake([
            'api.pwnedpasswords.com/range/' . $prefix => Http::response(
                "0000000000000000000000000000000000A:3\r\n{$suffix}:42\r\n",
                200,
            ),
        ]);

        $rule = (new RejectCommonPasswords(checkHibp: true));

        $this->assertTrue($this->fails($rule, $password));
    }

    public function test_hibp_never_transmits_the_full_password_only_the_sha1_prefix(): void
    {
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);

        $password = 'NeverLeakThisPassword!2026';
        $expectedPrefix = strtoupper(substr(sha1($password), 0, 5));
        $suffix = strtoupper(substr(sha1($password), 5));

        $this->fails(new RejectCommonPasswords(checkHibp: true), $password);

        Http::assertSent(function ($request) use ($password, $expectedPrefix, $suffix) {
            $url = $request->url();
            $body = $request->body();

            // The outgoing request carries ONLY the 5-char prefix in the path...
            $this->assertSame('https://api.pwnedpasswords.com/range/' . $expectedPrefix, $url);
            // ...and never the plaintext password, nor the SHA-1 suffix, anywhere.
            $this->assertStringNotContainsString($password, $url . $body);
            $this->assertStringNotContainsString($suffix, $url . $body);

            return true;
        });
    }
}
