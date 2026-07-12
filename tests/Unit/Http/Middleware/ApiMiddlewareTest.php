<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Http\Middleware\ApiMiddleware;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Concrete subclass that does not override mutateKey(), so it exercises the base
 * class's default identity transform.
 */
class IdentityApiMiddleware extends ApiMiddleware
{
    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function run(array $data): array
    {
        return $this->mutateKeys($data);
    }
}

#[Group('http')]
class ApiMiddlewareTest extends TestCase
{
    public function test_default_mutate_key_is_an_identity_transform(): void
    {
        $middleware = new IdentityApiMiddleware();

        $payload = [
            'user_id' => 1,
            'displayName' => 'Jane',
            'nested_block' => ['a_b' => 2, 'cD' => 3],
        ];

        // No key casing convention is applied: keys survive verbatim, nested.
        self::assertSame($payload, $middleware->run($payload));
    }

    public function test_default_transform_preserves_list_indices(): void
    {
        $middleware = new IdentityApiMiddleware();

        $payload = [['item_id' => 1], ['item_id' => 2]];

        self::assertSame($payload, $middleware->run($payload));
    }
}
