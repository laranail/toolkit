<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Regression;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Facades\Toolkit;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Behavioural parity snapshots — they pin the *intended* (corrected) output
 * contracts that survive the migration/restructure, exercised through the
 * unified Toolkit facade. Per-module behaviour is covered in depth by the
 * module suites; these lock the cross-cutting, deterministic contracts.
 */
#[Group('regression')]
class ParityTest extends TestCase
{
    public function test_gravatar_url_contract_is_stable(): void
    {
        $email = 'parity@example.com';
        $hash = md5($email); // lower-cased, trimmed — already canonical here

        $url = Toolkit::gravatar()->setEmail($email)->setSize(80)->generate();

        $this->assertStringStartsWith('https://secure.gravatar.com/avatar/' . $hash, $url);
        $this->assertStringContainsString('s=80', $url);
    }

    public function test_gravatar_defaults_to_https(): void
    {
        $url = Toolkit::gravatar()->setEmail('parity@example.com')->generate();

        $this->assertStringStartsWith('https://', $url);
    }

    public function test_avatar_data_uri_is_a_png_data_url(): void
    {
        $dataUri = Toolkit::avatar()->setName('Parity Tester')->setSize(64, 64)->generateDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        // The payload after the comma must be valid base64 of a PNG.
        $binary = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($binary);
        $this->assertSame("\x89PNG", substr((string) $binary, 0, 4));
    }
}
