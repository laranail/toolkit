<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Toolkit\Services\CacheService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * `keyFromRequest()` hands out a key that a caller will cache a *response*
 * under, so every way two different requests can share one is a way one user's
 * response reaches another.
 *
 * Three of them existed. The digest was MD5 over attacker-controlled,
 * length-prefixed input; the HTTP method was not in the key at all; and there
 * was no seam for the caller to add what the response actually varies by.
 */
final class RequestCacheKeyTest extends TestCase
{
    #[Test]
    public function the_key_is_a_sha256_digest(): void
    {
        $this->swapRequest('GET', 'https://example.test/reports');

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $this->service()->keyFromRequest());
    }

    #[Test]
    public function the_same_request_yields_the_same_key(): void
    {
        $this->swapRequest('GET', 'https://example.test/reports', ['q' => 'sales']);

        $service = $this->service();

        self::assertSame($service->keyFromRequest(), $service->keyFromRequest());
    }

    #[Test]
    public function the_http_method_is_part_of_the_key(): void
    {
        // The collision that needed no cryptography: GET and POST to one URL
        // with identical input hashed identically, so a POST response could be
        // served to a GET.
        $this->swapRequest('GET', 'https://example.test/orders', ['id' => '7']);
        $get = $this->service()->keyFromRequest();

        $this->swapRequest('POST', 'https://example.test/orders', ['id' => '7']);
        $post = $this->service()->keyFromRequest();

        self::assertNotSame($get, $post);
    }

    #[Test]
    public function the_url_is_part_of_the_key(): void
    {
        $this->swapRequest('GET', 'https://example.test/a', ['q' => '1']);
        $a = $this->service()->keyFromRequest();

        $this->swapRequest('GET', 'https://example.test/b', ['q' => '1']);
        $b = $this->service()->keyFromRequest();

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function argument_order_does_not_change_the_key(): void
    {
        // Not security — cache efficiency. The same logical request arriving
        // with its query parameters in a different order used to occupy a
        // second entry and miss the first.
        $this->swapRequest('GET', 'https://example.test/s', ['a' => '1', 'b' => '2']);
        $forwards = $this->service()->keyFromRequest();

        $this->swapRequest('GET', 'https://example.test/s', ['b' => '2', 'a' => '1']);
        $backwards = $this->service()->keyFromRequest();

        self::assertSame($forwards, $backwards);
    }

    #[Test]
    public function nested_argument_order_does_not_change_the_key_either(): void
    {
        $this->swapRequest('GET', 'https://example.test/s', ['f' => ['x' => '1', 'y' => '2']]);
        $forwards = $this->service()->keyFromRequest();

        $this->swapRequest('GET', 'https://example.test/s', ['f' => ['y' => '2', 'x' => '1']]);
        $backwards = $this->service()->keyFromRequest();

        self::assertSame($forwards, $backwards);
    }

    #[Test]
    public function list_order_is_preserved(): void
    {
        // `?tags[]=a&tags[]=b` is a different request from `?tags[]=b&tags[]=a`.
        // Sorting lists as well as maps would merge two distinct responses,
        // which is the bug this method is being fixed for, in reverse.
        $this->swapRequest('GET', 'https://example.test/s', ['tags' => ['a', 'b']]);
        $ab = $this->service()->keyFromRequest();

        $this->swapRequest('GET', 'https://example.test/s', ['tags' => ['b', 'a']]);
        $ba = $this->service()->keyFromRequest();

        self::assertNotSame($ab, $ba);
    }

    #[Test]
    public function vary_separates_two_users_issuing_the_identical_request(): void
    {
        // The leak the key cannot close on its own, and the seam that lets the
        // caller close it.
        $this->swapRequest('GET', 'https://example.test/dashboard');

        $service = $this->service();

        self::assertNotSame(
            $service->keyFromRequest(['user' => 1]),
            $service->keyFromRequest(['user' => 2]),
        );
    }

    #[Test]
    public function vary_order_does_not_change_the_key(): void
    {
        $this->swapRequest('GET', 'https://example.test/dashboard');

        $service = $this->service();

        self::assertSame(
            $service->keyFromRequest(['user' => 1, 'locale' => 'en']),
            $service->keyFromRequest(['locale' => 'en', 'user' => 1]),
        );
    }

    #[Test]
    public function an_empty_vary_is_the_no_argument_call(): void
    {
        $this->swapRequest('GET', 'https://example.test/dashboard');

        $service = $this->service();

        self::assertSame($service->keyFromRequest(), $service->keyFromRequest([]));
    }

    private function service(): CacheService
    {
        return new CacheService(60, []);
    }

    private function swapRequest(string $method, string $uri, array $input = []): void
    {
        $this->app->instance('request', Request::create($uri, $method, $input));
    }
}
