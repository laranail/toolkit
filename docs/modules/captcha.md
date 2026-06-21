# Captcha module

Verify CAPTCHA tokens against reCAPTCHA, hCaptcha, or Cloudflare Turnstile
behind a single `CaptchaProviderInterface`. Bound through a deferred provider
(alias `laranail.captcha`, facade `Captcha`). The provider used is chosen by
`config('laranail.toolkit.captcha.default_provider')` (`recaptcha` by default).

```php
use Simtabi\Laranail\Toolkit\Modules\Captcha\Facades\Captcha;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Services\CaptchaService;
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
// config/laranail-toolkit.php → laranail.toolkit.captcha
'default_provider' => env('LARANAIL_CAPTCHA_DEFAULT_PROVIDER', 'recaptcha'),
'recaptcha' => ['site_key' => ..., 'secret_key' => ..., 'min_score' => 0.5, 'timeout' => 30],
'turnstile' => ['site_key' => ..., 'secret_key' => ..., 'timeout' => 30],
'hcaptcha'  => ['site_key' => ..., 'secret_key' => ..., 'timeout' => 30],
'behavior'  => ['allow_unconfigured' => ..., 'log_failures' => ..., 'cache_duration' => ..., 'max_attempts_per_hour' => ...],
```

## Security: fails closed

Provider resolution is restricted to a fixed allow-list (`recaptcha`,
`turnstile`, `hcaptcha`) — a name from config or user input can never
instantiate an arbitrary class; an unknown name throws `InvalidArgumentException`.

Verification **fails closed**: any transport error, non-2xx response,
malformed body, or unconfigured provider yields a *failed*
`CaptchaVerificationResult` rather than throwing or reporting success. Never
treat an exception or missing config as a pass.

[← Docs index](../../README.md#documentation)
