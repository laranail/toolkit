<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Http\Concerns\MutatesPayloadKeys;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Exposes the protected concern methods so they can be exercised in isolation.
 */
class PayloadKeyMutator
{
    use MutatesPayloadKeys;

    /**
     * @param array<array-key, mixed>  $data
     * @param callable(string): string $transform
     *
     * @return array<array-key, mixed>
     */
    public function mutate(array $data, callable $transform): array
    {
        return $this->mutatePayloadKeys($data, $transform);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function camel(array $data): array
    {
        return $this->camelCaseKeys($data);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function snake(array $data): array
    {
        return $this->snakeCaseKeys($data);
    }
}

#[Group('http')]
class MutatesPayloadKeysTest extends TestCase
{
    public function test_camel_case_keys_rewrites_nested_string_keys(): void
    {
        $mutator = new PayloadKeyMutator();

        $result = $mutator->camel([
            'first_name' => 'Jane',
            'nested_data' => ['inner_key' => 1],
        ]);

        self::assertSame(
            ['firstName' => 'Jane', 'nestedData' => ['innerKey' => 1]],
            $result,
        );
    }

    public function test_snake_case_keys_rewrites_nested_string_keys(): void
    {
        $mutator = new PayloadKeyMutator();

        $result = $mutator->snake([
            'firstName' => 'Jane',
            'nestedData' => ['innerKey' => 1],
        ]);

        self::assertSame(
            ['first_name' => 'Jane', 'nested_data' => ['inner_key' => 1]],
            $result,
        );
    }

    public function test_integer_list_indices_are_preserved(): void
    {
        $mutator = new PayloadKeyMutator();

        $result = $mutator->camel([
            ['user_id' => 1],
            ['user_id' => 2],
        ]);

        self::assertSame([0, 1], array_keys($result));
        self::assertSame([['userId' => 1], ['userId' => 2]], $result);
    }

    public function test_empty_payload_returns_empty_array(): void
    {
        $mutator = new PayloadKeyMutator();

        self::assertSame([], $mutator->camel([]));
        self::assertSame([], $mutator->snake([]));
        self::assertSame([], $mutator->mutate([], static fn (string $key): string => $key));
    }

    public function test_json_resource_values_are_resolved_then_key_rewritten(): void
    {
        $resource = new class([]) extends JsonResource
        {
            /**
             * @param Request $request
             *
             * @return array<string, mixed>
             */
            public function toArray($request): array
            {
                return ['inner_key' => 'value'];
            }
        };

        $mutator = new PayloadKeyMutator();

        $result = $mutator->camel(['user_resource' => $resource]);

        self::assertSame(['userResource' => ['innerKey' => 'value']], $result);
    }
}
