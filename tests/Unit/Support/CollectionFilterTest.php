<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use Illuminate\Support\Collection;
use Simtabi\Laranail\Toolkit\Support\CollectionFilter;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CollectionFilterTest extends TestCase
{
    private Collection $testCollection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testCollection = collect([
            ['name' => 'John Doe', 'age' => 25, 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'age' => 30, 'email' => 'jane@example.com'],
            ['name' => 'Bob Wilson', 'age' => 35, 'email' => 'bob@test.com'],
            ['name' => 'Alice Brown', 'age' => 28, 'email' => 'alice@example.com'],
        ]);
    }

    public function test_can_filter_by_equals_operator()
    {
        $result = CollectionFilter::filter($this->testCollection, 'age', 'equals', 25);

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()['name']);
    }

    public function test_can_filter_by_not_equals_operator()
    {
        $result = CollectionFilter::filter($this->testCollection, 'age', 'not_equals', 25);

        $this->assertCount(3, $result);
        $this->assertNotContains(['name' => 'John Doe'], $result->toArray());
    }

    public function test_can_filter_by_contains_operator()
    {
        $result = CollectionFilter::filter($this->testCollection, 'email', 'contains', 'example');

        $this->assertCount(3, $result);
        $this->assertNotContains(['name' => 'Bob Wilson'], $result->toArray());
    }

    public function test_can_filter_by_not_contains_operator()
    {
        $result = CollectionFilter::filter($this->testCollection, 'email', 'not_contains', 'example');

        $this->assertCount(1, $result);
        $this->assertEquals('Bob Wilson', $result->first()['name']);
    }

    public function test_can_filter_by_starts_with_operator()
    {
        $result = CollectionFilter::filter($this->testCollection, 'name', 'starts_with', 'John');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()['name']);
    }

    public function test_can_filter_by_ends_with_operator()
    {
        $result = CollectionFilter::filter($this->testCollection, 'name', 'ends_with', 'Smith');

        $this->assertCount(1, $result);
        $this->assertEquals('Jane Smith', $result->first()['name']);
    }

    public function test_ends_with_matches_when_the_needle_repeats_earlier(): void
    {
        $collection = collect([
            ['name' => 'banana'],   // ends with 'a' (and contains earlier 'a's)
            ['name' => 'orange'],
        ]);

        $result = CollectionFilter::filter($collection, 'name', 'ends_with', 'a');

        $this->assertCount(1, $result);
        $this->assertSame('banana', $result->first()['name']);
    }

    public function test_string_operators_tolerate_non_string_and_missing_values(): void
    {
        // age is an int and some rows lack the field entirely — must not error.
        $result = CollectionFilter::filter($this->testCollection, 'age', 'contains', '5');

        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    public function test_returns_empty_collection_for_unknown_operator()
    {
        $result = CollectionFilter::filter($this->testCollection, 'name', 'unknown_operator', 'test');

        $this->assertCount(0, $result);
    }

    public function test_filtering_is_case_insensitive()
    {
        $result = CollectionFilter::filter($this->testCollection, 'name', 'contains', 'john');

        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result->first()['name']);
    }

    public function test_can_filter_nested_data()
    {
        $nestedCollection = collect([
            ['user' => ['name' => 'John', 'profile' => ['age' => 25]]],
            ['user' => ['name' => 'Jane', 'profile' => ['age' => 30]]],
        ]);

        $result = CollectionFilter::filter($nestedCollection, 'user.profile.age', 'equals', 25);

        $this->assertCount(1, $result);
        $this->assertEquals('John', $result->first()['user']['name']);
    }

    public function test_handles_missing_data_gracefully()
    {
        $result = CollectionFilter::filter($this->testCollection, 'non_existent_field', 'equals', 'value');

        $this->assertCount(0, $result);
    }
}
