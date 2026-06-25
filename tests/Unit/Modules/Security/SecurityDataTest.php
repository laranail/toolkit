<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Security;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Security\SecurityData;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class SecurityDataTest extends TestCase
{
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

    public function test_loads_package_default_without_publishing(): void
    {
        // No published override exists in the test app, so the accessor must
        // still resolve the package default config/security.php via its
        // __DIR__-relative path.
        $published = config_path('laranail-toolkit-security.php');
        $this->assertFileDoesNotExist($published);

        $this->assertCount(7776, SecurityData::passphraseWords());
        $this->assertNotEmpty(SecurityData::commonPasswords());
    }
}
