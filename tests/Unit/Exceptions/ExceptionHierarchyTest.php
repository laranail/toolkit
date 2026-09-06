<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Exceptions\CollectionItemNotFound;
use Simtabi\Laranail\Toolkit\Exceptions\FileTooLargeException;
use Simtabi\Laranail\Toolkit\Exceptions\ImmutableDataException;
use Simtabi\Laranail\Toolkit\Exceptions\InvalidPathException;
use Simtabi\Laranail\Toolkit\Exceptions\LaranailException;
use Simtabi\Laranail\Toolkit\Exceptions\ModelException;

class ExceptionHierarchyTest extends TestCase
{
    public function test_invalid_path_factories(): void
    {
        $this->assertInstanceOf(RuntimeException::class, InvalidPathException::create('/x'));
        $this->assertStringContainsString('Directory traversal', InvalidPathException::directoryTraversal('/x')->getMessage());
        $this->assertStringContainsString('Null byte', InvalidPathException::nullByteDetected('/x')->getMessage());
        $this->assertStringContainsString('outside allowed', InvalidPathException::outsideAllowedDirectory('/x')->getMessage());
        $this->assertStringContainsString('invalid characters', InvalidPathException::invalidCharacters('/x')->getMessage());
    }

    public function test_file_too_large_formats_bytes_human_readable(): void
    {
        $message = FileTooLargeException::create('/big.txt', 1_572_864, 1_048_576)->getMessage();

        $this->assertStringContainsString('/big.txt', $message);
        $this->assertStringContainsString('1.50 MB', $message);
        $this->assertStringContainsString('1.00 MB', $message);
    }

    public function test_file_too_large_handles_zero_and_huge_sizes(): void
    {
        // Hardened against the legacy unit-array overflow (>= 1 TB) and zero.
        $this->assertStringContainsString('0.00 B', FileTooLargeException::create('/a', 0, 10)->getMessage());
        $this->assertStringContainsString('TB', FileTooLargeException::create('/a', 5 * (1024 ** 4), 10)->getMessage());
    }

    public function test_model_exception_factories_carry_context(): void
    {
        $missing = ModelException::missingPrimaryKey('App\\Models\\User');
        $this->assertSame(3001, $missing->getCode());
        $this->assertSame(['model' => 'App\\Models\\User'], $missing->getContext());

        $notFound = ModelException::notFound('App\\Models\\User', 7);
        $this->assertSame(3002, $notFound->getCode());
        $this->assertSame(404, $notFound->getStatus());
        $this->assertStringContainsString('7', $notFound->getMessage());

        $invalid = ModelException::invalidState('App\\Models\\User', 'bad');
        $this->assertSame(3003, $invalid->getCode());
    }

    public function test_model_not_found_stringifies_non_scalar_identifier_safely(): void
    {
        // Legacy interpolated the identifier directly and would fatal on arrays.
        $exception = ModelException::notFound('App\\Models\\User', ['id' => 1]);

        $this->assertStringContainsString('array', $exception->getMessage());
        $this->assertSame(['id' => 1], $exception->getContext()['identifier']);
    }

    public function test_domain_exceptions_extend_laranail_exception(): void
    {
        $this->assertInstanceOf(LaranailException::class, ModelException::invalidState('M', 'r'));
    }

    public function test_trivial_marker_exceptions_have_helpful_factories(): void
    {
        $this->assertStringContainsString('immutable', ImmutableDataException::forProperty('name')->getMessage());
        $this->assertStringContainsString('key: 9', CollectionItemNotFound::forKey(9)->getMessage());
    }
}
