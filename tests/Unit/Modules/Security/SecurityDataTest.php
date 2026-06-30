<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Security;

use PHPUnit\Framework\Attributes\Group;
use ReflectionProperty;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Modules\Security\SecurityData;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class SecurityDataTest extends TestCase
{
    protected function tearDown(): void
    {
        // The accessor caches the loaded config in a static; reset it so a test
        // that overrides the `laranail.toolkit.security` config never leaks into
        // the next test (which expects the real bundled datasets).
        $this->resetSecurityDataCache();

        parent::tearDown();
    }

    private function resetSecurityDataCache(): void
    {
        $property = new ReflectionProperty(SecurityData::class, 'config');
        $property->setValue(null, null);
    }

    public function test_common_passwords_is_non_empty_deduped_and_lowercased(): void
    {
        $passwords = SecurityData::commonPasswords();

        $this->assertNotEmpty($passwords);
        $this->assertSame(array_values(array_unique($passwords)), $passwords, 'list must be deduplicated');

        foreach ($passwords as $password) {
            $this->assertSame(strtolower($password), $password, "entry [{$password}] must be lowercased");
        }

        // Spot-check a couple of canonical entries.
        $this->assertContains('password', $passwords);
        $this->assertContains('123456', $passwords);
    }

    public function test_passphrase_words_contains_exactly_7776_entries(): void
    {
        $words = SecurityData::passphraseWords();

        $this->assertCount(SecurityData::WORDLIST_SIZE, $words);
        $this->assertCount(7776, $words);
        $this->assertContains('abacus', $words);
        $this->assertContains('zoom', $words);
    }

    public function test_redact_keys_includes_password_and_token(): void
    {
        $keys = SecurityData::redactKeys();

        $this->assertNotEmpty($keys);
        $this->assertContains('password', $keys);
        $this->assertContains('token', $keys);
        $this->assertContains('secret', $keys);
    }

    public function test_reads_the_merged_security_config_namespace(): void
    {
        // The datasets are merged under `laranail.toolkit.security` and the
        // accessor reads them from there (no separate published file).
        $this->assertCount(7776, config('laranail.toolkit.security.passphrases.wordlist'));

        $this->assertCount(7776, SecurityData::passphraseWords());
        $this->assertNotEmpty(SecurityData::commonPasswords());
    }

    public function test_passphrase_words_guards_against_a_wrong_sized_wordlist(): void
    {
        config()->set('laranail.toolkit.security.passphrases.wordlist', ['only', 'two', 'words']);
        $this->resetSecurityDataCache();

        try {
            SecurityData::passphraseWords();
            $this->fail('Expected a RuntimeException for a wrong-sized wordlist.');
        } catch (RuntimeException $e) {
            $this->assertSame('EFF wordlist must contain exactly 7776 words, found 3.', $e->getMessage());
        }
    }

    public function test_a_non_array_section_yields_an_empty_list(): void
    {
        config()->set('laranail.toolkit.security.passwords', 'not-an-array');
        $this->resetSecurityDataCache();

        $this->assertSame([], SecurityData::commonPasswords());
    }

    public function test_a_non_array_entry_within_a_section_yields_an_empty_list(): void
    {
        config()->set('laranail.toolkit.security.passwords.common', 'not-a-list');
        $this->resetSecurityDataCache();

        $this->assertSame([], SecurityData::commonPasswords());
    }

    public function test_string_list_drops_non_string_entries(): void
    {
        config()->set('laranail.toolkit.security.passwords.common', ['keep', 123, null, 'also-keep', ['nested']]);
        $this->resetSecurityDataCache();

        $this->assertSame(['keep', 'also-keep'], SecurityData::commonPasswords());
    }

    public function test_non_array_redact_keys_yield_an_empty_list(): void
    {
        config()->set('laranail.toolkit.security.redact_keys', 'nope');
        $this->resetSecurityDataCache();

        $this->assertSame([], SecurityData::redactKeys());
    }

    public function test_falls_back_to_the_bundled_config_file_when_no_merged_config_exists(): void
    {
        // An empty merged config forces the framework-free file fallback, which
        // resolves the bundled `config/security.php` via a __DIR__-relative path.
        config()->set('laranail.toolkit.security', []);
        $this->resetSecurityDataCache();

        $this->assertNotEmpty(SecurityData::commonPasswords());
        $this->assertCount(7776, SecurityData::passphraseWords());
        $this->assertContains('password', SecurityData::redactKeys());
    }
}
