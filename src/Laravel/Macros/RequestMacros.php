<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Laravel\Macros;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the toolkit's general-purpose Request macros.
 *
 * Trivial one-to-one aliases of native Request methods (userAgent(), secure(),
 * ips(), bearerToken(), ...) are intentionally omitted, as are the legacy
 * macros that recursed into the native method of the same name.
 */
final class RequestMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRequestMacros();
    }

    private function registerRequestMacros(): void
    {
        Request::macro('expectsJsonOrAjax', function (): bool {
            /** @var Request $this */
            return $this->wantsJson() || $this->ajax();
        });

        Request::macro('isBot', function (): bool {
            /** @var Request $this */
            $userAgent = strtolower($this->userAgent() ?? '');
            $bots = ['bot', 'crawl', 'slurp', 'spider', 'mediapartners'];

            foreach ($bots as $bot) {
                if (str_contains($userAgent, $bot)) {
                    return true;
                }
            }

            return false;
        });

        Request::macro('isFromMobile', function (): bool {
            /** @var Request $this */
            $userAgent = strtolower($this->userAgent() ?? '');
            $mobileKeywords = ['mobile', 'android', 'iphone', 'ipad', 'windows phone'];

            foreach ($mobileKeywords as $keyword) {
                if (str_contains($userAgent, $keyword)) {
                    return true;
                }
            }

            return false;
        });

        Request::macro('hasFiles', function (array $keys): bool {
            /** @var Request $this */
            $request = $this;

            foreach ($keys as $key) {
                if (!$request->hasFile($key)) {
                    return false;
                }
            }

            return true;
        });

        Request::macro('hasValidFile', function (string $key): bool {
            /** @var Request $this */
            if (!$this->hasFile($key)) {
                return false;
            }

            $file = $this->file($key);

            return $file instanceof UploadedFile && $file->isValid();
        });

        Request::macro('getReferer', function (?string $default = null): ?string {
            /** @var Request $this */
            return $this->header('referer', $default);
        });

        Request::macro('isFromDomain', function (string $domain): bool {
            /** @var Request $this */
            $referer = $this->header('referer');

            if ($referer === null || $referer === '') {
                return false;
            }

            return str_contains($referer, $domain);
        });

        Request::macro('isJsonRequest', function (): bool {
            /** @var Request $this */
            return $this->isJson() || $this->wantsJson();
        });

        Request::macro('onlyFilled', function (array $keys): array {
            /** @var Request $this */
            return array_filter($this->only($keys), static fn (mixed $value): bool => filled($value));
        });

        Request::macro('hasAny', function (array $keys): bool {
            /** @var Request $this */
            $request = $this;

            foreach ($keys as $key) {
                if ($request->has($key)) {
                    return true;
                }
            }

            return false;
        });

        Request::macro('mergeIfMissing', function (array $values): Request {
            /** @var Request $this */
            $request = $this;

            foreach ($values as $key => $value) {
                if (!$request->has($key)) {
                    $request->merge([$key => $value]);
                }
            }

            return $request;
        });
    }
}
