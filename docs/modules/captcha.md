# Captcha module

Verify CAPTCHA tokens against reCAPTCHA, hCaptcha, Cloudflare Turnstile,
Friendly Captcha, or a no-op Null provider behind a single
`CaptchaProviderInterface`. Bound through a deferred provider (alias
`laranail.captcha`, facade `Captcha`). The provider used is chosen by
`config('laranail-toolkit-captcha.default_provider')` (`recaptcha` by default).

```php
use Simtabi\Laranail\Toolkit\Modules\Captcha\Captcha;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaService;
```

## Verify a token

```php
$result = Captcha::verify($request->input('captcha-token'));

if ($result->isSuccess()) {
    // proceed
} else {
    $errors = $result->getErrors();
    $first  = $result->getFirstError();
}
```

`verify()` accepts options and an explicit provider/IP:

```php
Captcha::verify(
    token: $token,
    options: ['action' => 'login'],   // e.g. reCAPTCHA v3 action
    provider: 'turnstile',
    remoteIp: $request->ip(),
);
```

Other `CaptchaService` methods: `getProvider(?string $name = null)`,
`registerProvider(...)`, `getSiteKey(?string $provider = null)` (for the frontend
widget), `hasProvider(string $name)`, `setDefaultProvider(string $name)`,
`getDefaultProvider()`, `getProviderNames()`, `hasConfiguredProvider()`, and
`verifyWithAllProviders(...)`.

## Result object

`verify()` returns an immutable `CaptchaVerificationResult`:

| Member | |
|--------|---|
| `isSuccess()` / `isFailure()` | Outcome. |
| `getScore()` / `score()` | Score (e.g. reCAPTCHA v3). |
| `getErrors()` / `errorCodes()` | Error codes. |
| `getFirstError()` | First error or `null`. |
| `getProviderName()` | Which provider answered. |
| `getContext()` | Raw provider context. |
| `toArray()` | Serializable form. |

## Configuration

```php
// config/captcha.php → laranail-toolkit-captcha
'default_provider' => env('LARANAIL_CAPTCHA_DEFAULT_PROVIDER', 'recaptcha'),
'recaptcha' => ['site_key' => env('RECAPTCHA_SITE_KEY'), 'secret_key' => env('RECAPTCHA_SECRET_KEY'), 'min_score' => 0.5, 'timeout' => 30],
'turnstile' => ['site_key' => env('TURNSTILE_SITE_KEY'), 'secret_key' => env('TURNSTILE_SECRET_KEY'), 'timeout' => 30],
'hcaptcha'  => ['site_key' => env('HCAPTCHA_SITE_KEY'), 'secret_key' => env('HCAPTCHA_SECRET_KEY'), 'timeout' => 30],
'friendly_captcha' => ['site_key' => env('FRIENDLY_CAPTCHA_SITE_KEY'), 'secret_key' => env('FRIENDLY_CAPTCHA_API_KEY'), 'use_eu_endpoint' => false, 'timeout' => 30],
'null'      => ['site_key' => env('NULL_CAPTCHA_SITE_KEY', 'null-site-key')],
'behavior'  => ['allow_unconfigured' => false, 'log_failures' => true, 'cache_duration' => 0, 'max_attempts_per_hour' => 100],
```

Per-provider env keys: `{RECAPTCHA,TURNSTILE,HCAPTCHA}_SITE_KEY` /
`_SECRET_KEY` (+ `_TIMEOUT`, and `RECAPTCHA_MIN_SCORE`); Friendly Captcha uses
`FRIENDLY_CAPTCHA_SITE_KEY` / `FRIENDLY_CAPTCHA_API_KEY` /
`FRIENDLY_CAPTCHA_USE_EU_ENDPOINT` / `FRIENDLY_CAPTCHA_TIMEOUT`; behaviour gates
use `CAPTCHA_ALLOW_UNCONFIGURED`, `CAPTCHA_LOG_FAILURES`,
`CAPTCHA_CACHE_DURATION`, `CAPTCHA_MAX_ATTEMPTS_PER_HOUR`.

### Friendly Captcha

A privacy-first, proof-of-work CAPTCHA. Verification posts the solution as JSON
to `https://global.frcapi.com/api/v2/captcha/siteverify` (or the EU-resident
`https://eu.frcapi.com/...` when `use_eu_endpoint` is set), authenticating with
the API key via the `X-API-Key` header. It returns no score, so a pass yields
`1.0`. `isConfigured()` requires both the site key and the API key. The
`secret_key` config slot carries the **API key**.

Env keys: `FRIENDLY_CAPTCHA_SITE_KEY`, `FRIENDLY_CAPTCHA_API_KEY`,
`FRIENDLY_CAPTCHA_USE_EU_ENDPOINT`, `FRIENDLY_CAPTCHA_TIMEOUT`.

### Null provider

A no-op driver that performs **no external verification** and **always passes**
with a score of `1.0` and a `note` of `no external verification`. It is always
"configured" and makes no HTTP calls. Useful for local development, testing, and
honeypot-style flows that gate on another control.

> **WARNING:** the `null` provider offers no bot protection. Never use it in
> production unless another control (rate-limiting, a honeypot field, etc.)
> actually guards the request — otherwise every submission passes unchecked.

Env keys: `NULL_CAPTCHA_SITE_KEY` (defaults to `null-site-key`).

> Not shipped: GeeTest, Arkose Labs, and AWS WAF are intentionally skipped
> (non-REST / SDK-only / enterprise / infra-bound). mCaptcha is reCAPTCHA-API
> compatible and could reuse the reCAPTCHA driver shape if ever needed.

## Security: fails closed

Provider resolution is restricted to a fixed allow-list (`recaptcha`,
`turnstile`, `hcaptcha`, `friendly_captcha`, `null`) — a name from config or
user input can never instantiate an arbitrary class; an unknown name throws
`InvalidArgumentException`.

Verification **fails closed**: any transport error, non-2xx response,
malformed body, or unconfigured provider yields a *failed*
`CaptchaVerificationResult` rather than throwing or reporting success. Never
treat an exception or missing config as a pass.

[← Docs index](../../README.md#documentation)
