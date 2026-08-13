# Upgrade guide

## The captcha module moved to `laranail/captcha`

It outgrew a toolkit module. The replacement covers eleven providers rather than five, adds
environment-scoped credentials, a database-backed settings store, replay and hostname enforcement,
and an edge bot-management middleware — none of which belongs behind `Toolkit::captcha()`.

```diff
+   composer require laranail/captcha
```

- `Toolkit::captcha()` is removed. Use the `Captcha` facade, which `laranail/captcha` registers.
- `config('laranail.toolkit.captcha.*')` becomes `config('laranail.captcha.*')`, with per-provider
  credentials under an environment block. Run `php artisan laranail::captcha.install`.
- The `Captcha` alias is no longer registered here, so the two packages no longer collide.
