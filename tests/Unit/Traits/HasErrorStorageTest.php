<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Traits;

use Simtabi\Laranail\Toolkit\Services\Contracts\ErrorStorageServiceInterface;
use Simtabi\Laranail\Toolkit\Services\ErrorStorageService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasErrorStorage;

/**
 * Test double that exposes the protected {@see HasErrorStorage::addError()}.
 */
class HasErrorStorageFixture
{
    use HasErrorStorage;

    public function record(string $key, string $message): static
    {
        return $this->addError($key, $message);
    }
}

class HasErrorStorageTest extends TestCase
{
    public function test_interface_is_bound_to_the_concrete_service(): void
    {
        $this->assertInstanceOf(
            ErrorStorageService::class,
            app(ErrorStorageServiceInterface::class),
        );
    }

    public function test_trait_delegates_to_a_single_memoised_service(): void
    {
        $object = new HasErrorStorageFixture();

        $object->record('email', 'required')->record('email', 'invalid');

        // If the trait resolved a fresh service per call (the legacy bug) the
        // accumulated state below would be lost.
        $this->assertTrue($object->hasErrors());
        $this->assertSame(1, $object->getErrorCount());
        $this->assertSame(['required', 'invalid'], $object->getErrors('email'));
        $this->assertSame('required', $object->getFirstError());

        $object->clearErrors();
        $this->assertFalse($object->hasErrors());
    }
}
