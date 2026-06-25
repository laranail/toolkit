<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support\Security;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Support\Security\Token;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class TokenTest extends TestCase
{
    public function test_hex_encoding_produces_only_hex_chars(): void
    {
        $token = Token::unsigned()->encoding('hex')->length(16)->generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
        $this->assertSame(32, strlen($token)); // 16 bytes -> 32 hex chars
    }

    public function test_base64url_encoding_is_url_safe_with_no_padding(): void
    {
        $token = Token::unsigned()->encoding('base64url')->length(24)->generate();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
        $this->assertStringNotContainsString('=', $token);
        $this->assertStringNotContainsString('+', $token);
        $this->assertStringNotContainsString('/', $token);
    }

    public function test_base32_encoding_uses_the_rfc4648_alphabet(): void
    {
        $token = Token::unsigned()->encoding('base32')->length(20)->generate();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $token);
    }

    public function test_alphanum_encoding_is_mixed_case_alphanumeric(): void
    {
        $token = Token::unsigned()->encoding('alphanum')->length(40)->generate();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $token);
        $this->assertSame(40, strlen($token)); // one symbol per byte
    }

    public function test_numeric_encoding_is_digits_only(): void
    {
        $token = Token::unsigned()->encoding('numeric')->length(8)->generate();

        $this->assertMatchesRegularExpression('/^[0-9]+$/', $token);
        $this->assertSame(8, strlen($token));
    }

    public function test_prefix_is_prepended(): void
    {
        $token = Token::unsigned()->prefix('sk_live_')->encoding('hex')->length(16)->generate();

        $this->assertStringStartsWith('sk_live_', $token);
    }

    public function test_signed_round_trip_verifies_true(): void
    {
        $builder = Token::signed('s3cr3t')->encoding('hex')->length(32);

        $token = $builder->generate();

        $this->assertTrue($builder->verify($token));
    }

    public function test_signed_token_with_prefix_and_type_round_trips(): void
    {
        $builder = Token::signed('s3cr3t')->prefix('rt_')->type('reset')->encoding('base64url')->length(32);

        $token = $builder->generate();

        $this->assertStringStartsWith('rt_', $token);
        $this->assertTrue($builder->verify($token));
    }

    public function test_tampered_token_verifies_false(): void
    {
        $builder = Token::signed('s3cr3t')->encoding('hex')->length(32);
        $token = $builder->generate();

        $this->assertFalse($builder->verify($token . 'x'));
        $this->assertFalse($builder->verify(substr($token, 0, -1)));
    }

    public function test_wrong_secret_verifies_false(): void
    {
        $token = Token::signed('right-secret')->type('reset')->encoding('hex')->length(32)->generate();

        $this->assertFalse(Token::signed('wrong-secret')->type('reset')->encoding('hex')->length(32)->verify($token));
    }

    public function test_token_with_no_dot_verifies_false(): void
    {
        $this->assertFalse(Token::signed('s')->verify('not-a-token'));
    }

    public function test_unsigned_verify_throws(): void
    {
        $this->expectException(LogicException::class);

        Token::unsigned()->verify('whatever.mac');
    }

    public function test_empty_signing_secret_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Token::signed('');
    }

    public function test_length_guard_rejects_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Token::unsigned()->length(4);
    }

    public function test_length_guard_rejects_too_large(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Token::unsigned()->length(2048);
    }

    public function test_invalid_encoding_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Token::unsigned()->encoding('rot13');
    }

    public function test_negative_expiry_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Token::signed('s')->expiresIn(-1);
    }

    public function test_to_string_yields_a_token(): void
    {
        $token = (string) Token::unsigned()->encoding('hex')->length(16);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
    }

    public function test_builder_is_immutable(): void
    {
        $base = Token::unsigned()->encoding('hex')->length(16);
        $longer = $base->length(32);

        $this->assertSame(32, strlen($base->generate()));
        $this->assertSame(64, strlen($longer->generate()));
        $this->assertNotSame($base, $longer);
    }

    #[Group('security')]
    public function test_non_expired_signed_token_verifies_true(): void
    {
        $builder = Token::signed('s3cr3t')->expiresIn(3600)->encoding('hex')->length(16);
        $token = $builder->generate();

        $this->assertTrue($builder->verify($token));
    }

    #[Group('security')]
    public function test_expired_token_verifies_false(): void
    {
        $secret = 's3cr3t';
        $builder = Token::signed($secret)->expiresIn(60)->encoding('hex')->length(16);

        // Hand-build a structurally valid but already-expired token using the SAME
        // signing contract: signedBody = prefix . encoded . pastExpiry, MAC over it.
        $body = bin2hex(random_bytes(16));
        $pastExpiry = (string) (time() - 1);
        $signedBody = $body . '.' . $pastExpiry;
        $mac = rtrim(strtr(base64_encode(hash_hmac('sha256', $signedBody, $secret, true)), '+/', '-_'), '=');
        $expired = $signedBody . '.' . $mac;

        // The MAC is valid (so it passes hash_equals) but the expiry is in the past.
        $this->assertFalse($builder->verify($expired));
    }

    #[Group('security')]
    public function test_tampered_type_is_rejected_by_the_signature(): void
    {
        $builder = Token::signed('s3cr3t')->type('reset')->encoding('hex')->length(16);
        $token = $builder->generate();

        // Swap the type segment to 'verify' while keeping the original MAC.
        $tampered = str_replace('.reset.', '.verify.', $token);

        $this->assertNotSame($token, $tampered);
        $this->assertFalse($builder->verify($tampered));
    }

    #[Group('security')]
    public function test_tokens_are_high_entropy_with_no_collisions(): void
    {
        // A large sample drawn from random_bytes() must not collide, evidencing a
        // CSPRNG source rather than a seeded/weak generator.
        $seen = [];
        for ($i = 0; $i < 2000; $i++) {
            $seen[Token::unsigned()->encoding('hex')->length(16)->generate()] = true;
        }

        $this->assertCount(2000, $seen);
    }
}
