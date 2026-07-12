<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Toolkit\Services\ErrorStorageService;

class ErrorStorageServiceTest extends TestCase
{
    public function test_set_errors_wraps_a_string(): void
    {
        $service = ErrorStorageService::create()->setErrors('boom');

        $this->assertSame(['boom'], $service->getErrors());
        $this->assertTrue($service->hasErrors());
    }

    public function test_set_errors_merges_with_existing(): void
    {
        $service = ErrorStorageService::withErrors(['a'])->setErrors(['b']);

        $this->assertSame(['a', 'b'], $service->getErrors());
    }

    public function test_get_errors_by_key_returns_wrapped_value(): void
    {
        $service = ErrorStorageService::withErrors(['email' => 'invalid']);

        $this->assertSame(['invalid'], $service->getErrors('email'));
        $this->assertSame([], $service->getErrors('missing'));
    }

    public function test_add_error_keeps_first_then_merges_on_repeat_key(): void
    {
        $service = ErrorStorageService::create()
            ->addError('name', 'required')
            ->addError('name', 'too short');

        $this->assertSame(['required', 'too short'], $service->getErrors('name'));
    }

    public function test_count_and_clear(): void
    {
        $service = ErrorStorageService::withErrors(['a' => 1, 'b' => 2]);

        $this->assertSame(2, $service->getErrorCount());

        $service->clearErrors();

        $this->assertSame(0, $service->getErrorCount());
        $this->assertFalse($service->hasErrors());
        $this->assertNull($service->getFirstError());
    }

    public function test_get_first_error_unwraps_nested_arrays(): void
    {
        $service = ErrorStorageService::create()
            ->addError('name', 'required')
            ->addError('name', 'too short');

        // First stored key is "name" whose value is now an array.
        $this->assertSame('required', $service->getFirstError());
    }

    public function test_get_first_error_returns_scalar(): void
    {
        $service = ErrorStorageService::withErrors(['just one']);

        $this->assertSame('just one', $service->getFirstError());
    }
}
