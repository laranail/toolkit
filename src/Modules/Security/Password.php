<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Security;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Fluent, immutable random-password generator.
 *
 * Builds high-entropy random passwords from a configurable character-class pool
 * using a native CSPRNG ({@see random_int()} only — never `rand`/`mt_rand`/
 * `uniqid`/`Str::random`). Every chain method returns a fresh instance
 * (clone-and-mutate via {@see with()}), so a configured builder is reusable.
 *
 * Defaults follow NIST SP 800-63B / OWASP ASVS guidance: prefer length, draw
 * uniformly from the selected pool, and (optionally) guarantee class coverage
 * and a minimum entropy floor rather than relying on composition rules alone.
 *
 * Entropy is the Shannon worst-case estimate `length * log2(poolSize)` — the
 * information content of a uniformly random draw from the pool, in bits.
 *
 * @see https://pages.nist.gov/800-63-3/sp800-63b.html
 * @see https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
 */
final class Password implements \Stringable
{
    private const LOWERCASE = 'abcdefghijklmnopqrstuvwxyz';

    private const UPPERCASE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const DIGITS = '0123456789';

    private const SYMBOLS = '!@#$%^&*()-_=+[]{};:,.<>?';

    /** Ambiguous, easily-confused glyphs removed by {@see excludeAmbiguous()}. */
    private const AMBIGUOUS = '0O1lI';

    /** Bound on retries for the requireEachClass / minEntropy constraints. */
    private const MAX_ATTEMPTS = 1000;

    private int $length = 16;

    private bool $uppercase = true;

    private bool $lowercase = true;

    private bool $digits = true;

    private bool $symbols = true;

    private bool $excludeAmbiguous = false;

    private bool $requireEachClass = true;

    private float $minEntropy = 0.0;

    // --- Presets -------------------------------------------------------------

    /** All four classes, 20 chars, ambiguous glyphs removed, each class required. */
    public static function strong(): self
    {
        $instance = new self();
        $instance->length = 20;
        $instance->symbols = true;
        $instance->excludeAmbiguous = true;
        $instance->requireEachClass = true;

        return $instance;
    }

    /** Letters + digits, no symbols, 16 chars. */
    public static function alphanumeric(): self
    {
        $instance = new self();
        $instance->length = 16;
        $instance->symbols = false;

        return $instance;
    }

    /** Digits only, 6 chars — a numeric PIN/OTP. */
    public static function numeric(): self
    {
        $instance = new self();
        $instance->length = 6;
        $instance->uppercase = false;
        $instance->lowercase = false;
        $instance->digits = true;
        $instance->symbols = false;
        $instance->requireEachClass = false;

        return $instance;
    }

    /** Lowercase + digits, 12 chars — a simple, readable default. */
    public static function basic(): self
    {
        $instance = new self();
        $instance->length = 12;
        $instance->uppercase = false;
        $instance->symbols = false;

        return $instance;
    }

    // --- Chain (immutable) ---------------------------------------------------

    /**
     * Set the password length.
     *
     * @throws InvalidArgumentException when `$length` is < 1
     */
    public function length(int $length): self
    {
        if ($length < 1) {
            throw new InvalidArgumentException("length must be >= 1, got [{$length}].");
        }

        return $this->with(fn (self $c) => $c->length = $length);
    }

    /** Toggle the uppercase class `[A-Z]`. */
    public function uppercase(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->uppercase = $enabled);
    }

    /** Toggle the lowercase class `[a-z]`. */
    public function lowercase(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->lowercase = $enabled);
    }

    /** Toggle the digit class `[0-9]`. */
    public function digits(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->digits = $enabled);
    }

    /** Toggle the symbol class. */
    public function symbols(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->symbols = $enabled);
    }

    /** Drop visually-ambiguous glyphs (`0 O 1 l I`) from every selected class. */
    public function excludeAmbiguous(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->excludeAmbiguous = $enabled);
    }

    /** Require at least one character from each selected class in the result. */
    public function requireEachClass(bool $enabled = true): self
    {
        return $this->with(fn (self $c) => $c->requireEachClass = $enabled);
    }

    /**
     * Require the generated password to meet a minimum entropy floor (bits).
     *
     * @throws InvalidArgumentException when `$bits` is negative
     */
    public function minEntropy(float $bits): self
    {
        if ($bits < 0.0) {
            throw new InvalidArgumentException("minEntropy must be >= 0, got [{$bits}].");
        }

        return $this->with(fn (self $c) => $c->minEntropy = $bits);
    }

    // --- Terminals -----------------------------------------------------------

    /**
     * Generate the password.
     *
     * @throws LogicException   when no character class is selected
     * @throws RuntimeException when the requireEachClass / minEntropy constraints
     *                          cannot be satisfied within {@see MAX_ATTEMPTS}
     *                          (e.g. minEntropy(128) on a short lowercase-only pool)
     */
    public function generate(): string
    {
        $pools = $this->pools();
        $combined = implode('', $pools);

        if ($combined === '') {
            throw new LogicException('At least one character class must be selected.');
        }

        $this->guardEntropyAchievable($combined);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $password = $this->draw($pools, $combined);

            if ($this->requireEachClass && !$this->coversEachClass($password, $pools)) {
                continue;
            }

            return $password;
        }

        throw new RuntimeException(
            sprintf('Could not satisfy the password constraints within %d attempts.', self::MAX_ATTEMPTS),
        );
    }

    /**
     * Generate the password together with its entropy metadata.
     *
     * @return array{password: string, entropy: float, charset_size: int, length: int}
     */
    public function generateWithMetadata(): array
    {
        $password = $this->generate();
        $charsetSize = strlen(implode('', $this->pools()));

        return [
            'password' => $password,
            'entropy' => $this->entropyBits($charsetSize),
            'charset_size' => $charsetSize,
            'length' => $this->length,
        ];
    }

    /** Convenience: a {@see generate()}d password. */
    public function __toString(): string
    {
        return $this->generate();
    }

    // --- Internals -----------------------------------------------------------

    /**
     * The selected, ambiguity-filtered character pools, keyed by class.
     *
     * @return array<string, string>
     */
    private function pools(): array
    {
        $pools = [];

        if ($this->lowercase) {
            $pools['lower'] = $this->filterAmbiguous(self::LOWERCASE);
        }

        if ($this->uppercase) {
            $pools['upper'] = $this->filterAmbiguous(self::UPPERCASE);
        }

        if ($this->digits) {
            $pools['digits'] = $this->filterAmbiguous(self::DIGITS);
        }

        if ($this->symbols) {
            $pools['symbols'] = $this->filterAmbiguous(self::SYMBOLS);
        }

        return array_filter($pools, static fn (string $pool): bool => $pool !== '');
    }

    /** Strip ambiguous glyphs from a pool when {@see excludeAmbiguous()} is on. */
    private function filterAmbiguous(string $pool): string
    {
        if (!$this->excludeAmbiguous) {
            return $pool;
        }

        $ambiguous = str_split(self::AMBIGUOUS);

        return str_replace($ambiguous, '', $pool);
    }

    /**
     * Draw one full password uniformly from the combined pool.
     *
     * @param array<string, string> $pools
     */
    private function draw(array $pools, string $combined): string
    {
        $size = strlen($combined);
        $password = '';

        for ($i = 0; $i < $this->length; $i++) {
            $password .= $combined[random_int(0, $size - 1)];
        }

        return $password;
    }

    /**
     * True when `$password` contains at least one character from every pool.
     *
     * @param array<string, string> $pools
     */
    private function coversEachClass(string $password, array $pools): bool
    {
        return array_all($pools, fn (string $pool): bool => $this->containsAny($password, $pool));
    }

    /** True when any character of `$pool` appears in `$password`. */
    private function containsAny(string $password, string $pool): bool
    {
        $chars = str_split($pool);

        return array_any($chars, fn (string $char): bool => str_contains($password, $char));
    }

    /** Worst-case Shannon entropy of a uniform draw: `length * log2(poolSize)`. */
    private function entropyBits(int $charsetSize): float
    {
        if ($charsetSize <= 1) {
            return 0.0;
        }

        return $this->length * log($charsetSize, 2);
    }

    /**
     * Fail fast when the minimum-entropy floor is mathematically unreachable for
     * the configured pool and length — no amount of retrying could satisfy it.
     *
     * @throws RuntimeException when the entropy target is impossible
     */
    private function guardEntropyAchievable(string $combined): void
    {
        if ($this->minEntropy <= 0.0) {
            return;
        }

        if ($this->entropyBits(strlen($combined)) < $this->minEntropy) {
            throw new RuntimeException(sprintf(
                'minEntropy of %.2f bits is unreachable: %d chars over a %d-symbol pool yields at most %.2f bits.',
                $this->minEntropy,
                $this->length,
                strlen($combined),
                $this->entropyBits(strlen($combined)),
            ));
        }
    }

    /**
     * Return a mutated clone, keeping the builder immutable.
     *
     * @param callable(self): mixed $mutator
     */
    private function with(callable $mutator): self
    {
        $clone = clone $this;
        $mutator($clone);

        return $clone;
    }
}
