<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Exceptions\LaranailException;

class LaranailExceptionTest extends TestCase
{
    public function test_constructor_stores_structured_payload(): void
    {
        $exception = new LaranailException(
            message: 'boom',
            code: 42,
            context: ['a' => 1],
            meta: ['ref' => 'x'],
            userMessage: 'friendly',
            status: 500,
        );

        $this->assertSame('boom', $exception->getMessage());
        $this->assertSame(42, $exception->getCode());
        $this->assertSame(['a' => 1], $exception->getContext());
        $this->assertSame(['ref' => 'x'], $exception->getMeta());
        $this->assertSame('friendly', $exception->getUserMessage());
        $this->assertSame(500, $exception->getStatus());
    }

    public function test_from_array_folds_unknown_keys_into_meta(): void
    {
        $exception = LaranailException::fromArray([
            'message' => 'hi',
            'code' => 7,
            'context' => ['k' => 'v'],
            'extra' => 'folded',
        ]);

        $this->assertSame('hi', $exception->getMessage());
        $this->assertSame(7, $exception->getCode());
        $this->assertSame(['k' => 'v'], $exception->getContext());
        $this->assertSame(['extra' => 'folded'], $exception->getMeta());
    }

    public function test_from_array_defaults_message(): void
    {
        $exception = LaranailException::fromArray([]);

        $this->assertSame('Unexpected error', $exception->getMessage());
    }

    public function test_wrap_preserves_previous_and_code(): void
    {
        $previous = new RuntimeException('underlying', 99);

        $exception = LaranailException::wrap($previous, context: ['x' => 1]);

        $this->assertSame('underlying', $exception->getMessage());
        $this->assertSame(99, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(['x' => 1], $exception->getContext());
    }

    public function test_from_is_an_alias_for_wrap(): void
    {
        $previous = new RuntimeException('p');

        $exception = LaranailException::from($previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('p', $exception->getMessage());
    }

    public function test_fluent_helpers_mutate_and_return_self(): void
    {
        $exception = new LaranailException('m');

        $returned = $exception
            ->withContext('c', 1)
            ->mergeContext(['d' => 2])
            ->withMeta('m1', 'a')
            ->mergeMeta(['m2' => 'b'])
            ->withUserMessage('hello')
            ->withStatus(404);

        $this->assertSame($exception, $returned);
        $this->assertSame(['c' => 1, 'd' => 2], $exception->getContext());
        $this->assertSame(['m1' => 'a', 'm2' => 'b'], $exception->getMeta());
        $this->assertSame('hello', $exception->getUserMessage());
        $this->assertSame(404, $exception->getStatus());
    }

    public function test_to_array_includes_previous_and_optional_trace(): void
    {
        $previous = new RuntimeException('cause', 5);
        $exception = new LaranailException('m', 1, $previous, ['c' => 1]);

        $array = $exception->toArray();
        $this->assertSame(LaranailException::class, $array['type']);
        $this->assertSame('cause', $array['previous']['message']);
        $this->assertArrayNotHasKey('trace', $array);

        $withTrace = $exception->toArray(true);
        $this->assertArrayHasKey('trace', $withTrace);
        $this->assertArrayHasKey('file', $withTrace);
    }

    public function test_to_log_context_filters_empty_values(): void
    {
        $exception = new LaranailException('m');

        $logContext = $exception->toLogContext();

        $this->assertArrayHasKey('exception', $logContext);
        $this->assertArrayHasKey('asArray', $logContext);
        $this->assertArrayNotHasKey('context', $logContext);
        $this->assertArrayNotHasKey('meta', $logContext);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $exception = new LaranailException('m', 2, null, ['c' => 1]);

        $this->assertSame($exception->toArray(false), $exception->jsonSerialize());
    }

    public function test_to_string_summarises_the_exception(): void
    {
        $exception = new LaranailException('boom', 3)
            ->withStatus(500)
            ->withContext('c', 1);

        $string = (string) $exception;

        $this->assertStringContainsString('boom', $string);
        $this->assertStringContainsString('code:3', $string);
        $this->assertStringContainsString('status:500', $string);
        $this->assertStringContainsString('context=', $string);
    }
}
