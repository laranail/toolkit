<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoLiteralCredentials;

final class NoLiteralCredentialsTest extends TestCase
{
    use AssertsNoLiteralCredentials;

    public function test_it_keeps_literal_credentials_out_of_the_tests(): void
    {
        $this->assertNoLiteralCredentialsIn([dirname(__DIR__)], minFiles: 114);
    }

    public function test_it_names_every_generated_credential_with_a_single_word(): void
    {
        $this->assertCredentialPurposesAreWordsIn([dirname(__DIR__)], minFiles: 114);
    }
}
